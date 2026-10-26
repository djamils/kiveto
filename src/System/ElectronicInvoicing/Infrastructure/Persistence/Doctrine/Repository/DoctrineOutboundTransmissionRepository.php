<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Repository;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\ValueObject\Siren;
use App\System\ElectronicInvoicing\Domain\Exception\TransmissionNotFoundException;
use App\System\ElectronicInvoicing\Domain\Transmission\FlowId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmission;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionId;
use App\System\ElectronicInvoicing\Domain\Transmission\OutboundTransmissionRepositoryInterface;
use App\System\ElectronicInvoicing\Domain\Transmission\TrackingId;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionKind;
use App\System\ElectronicInvoicing\Domain\Transmission\TransmissionState;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\LifecycleEventEntity;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\OutboundTransmissionEntity;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Mapper\OutboundTransmissionMapper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

/**
 * Transmissions and their append-only lifecycle events: a saved transmission
 * adds the statuses it did not have yet, never rewrites the others.
 *
 * Two writers never overwrite each other: the version column is an
 * optimistic lock, a concurrent change fails the flush and Messenger retries
 * the message against fresh data. Two first requests of the same document
 * collide on its tracking id: the loser is retried and finds the winner's.
 */
final readonly class DoctrineOutboundTransmissionRepository implements OutboundTransmissionRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private OutboundTransmissionMapper $mapper,
        private UuidGeneratorInterface $uuids,
    ) {
    }

    public function save(OutboundTransmission $transmission): void
    {
        $id       = Uuid::fromString($transmission->id()->toString());
        $existing = $this->em->find(OutboundTransmissionEntity::class, $id);

        if (null === $existing) {
            $this->em->persist($this->mapper->toEntity($transmission));
        } else {
            $this->mapper->toEntity($transmission, $existing);
        }

        $stored = [];
        foreach ($this->events($id) as $event) {
            $stored[$event->getStatusCode() . '|' . $event->getSourceFlowId()] = true;
        }

        foreach ($transmission->lifecycle() as $event) {
            if (!isset($stored[$event->code->value . '|' . $event->sourceFlowId])) {
                $this->em->persist($this->mapper->eventToEntity($event, $transmission->id(), $this->uuids->generate()));
            }
        }

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            if (null !== $existing) {
                throw $e;
            }

            throw new RecoverableMessageHandlingException(\sprintf('Transmission of %s recorded concurrently.', $transmission->trackingId()->toString()), 0, $e);
        }
    }

    public function get(OutboundTransmissionId $id): OutboundTransmission
    {
        $entity = $this->em->find(OutboundTransmissionEntity::class, Uuid::fromString($id->toString()));

        if (null === $entity) {
            throw new TransmissionNotFoundException($id->toString());
        }

        return $this->toDomain($entity);
    }

    public function byTrackingId(TrackingId $trackingId): ?OutboundTransmission
    {
        return $this->findOne(['trackingId' => $trackingId->toString()]);
    }

    public function byFlowId(FlowId $flowId): ?OutboundTransmission
    {
        return $this->findOne(['flowId' => $flowId->toString()]);
    }

    public function byDocument(Siren $sellerSiren, TransmissionKind $kind, string $documentNumber): ?OutboundTransmission
    {
        return $this->findOne(['sellerSiren' => $sellerSiren->value(), 'kind' => $kind->value, 'documentNumber' => $documentNumber]);
    }

    public function awaitingCreditNotesOf(Siren $sellerSiren, string $invoiceNumber): array
    {
        $entities = $this->em->getRepository(OutboundTransmissionEntity::class)->findBy([
            'sellerSiren'             => $sellerSiren->value(),
            'kind'                    => TransmissionKind::CREDIT_NOTE->value,
            'precedingDocumentNumber' => $invoiceNumber,
            'state'                   => TransmissionState::AWAITING_INVOICE->value,
        ], ['documentDate' => 'ASC', 'documentNumber' => 'ASC']);

        return array_map($this->toDomain(...), $entities);
    }

    /**
     * @param array<string, string> $criteria
     */
    private function findOne(array $criteria): ?OutboundTransmission
    {
        $entity = $this->em->getRepository(OutboundTransmissionEntity::class)->findOneBy($criteria);

        return null === $entity ? null : $this->toDomain($entity);
    }

    private function toDomain(OutboundTransmissionEntity $entity): OutboundTransmission
    {
        // The row may have been marked by the submission schedule in SQL.
        $this->em->refresh($entity);

        return $this->mapper->toDomain($entity, $this->events($entity->getId()));
    }

    /**
     * @return list<LifecycleEventEntity>
     */
    private function events(Uuid $transmissionId): array
    {
        return $this->em->getRepository(LifecycleEventEntity::class)->findBy(['transmissionId' => $transmissionId], ['occurredAt' => 'ASC', 'statusCode' => 'ASC']);
    }
}
