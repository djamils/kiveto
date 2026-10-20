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
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\LifecycleEventEntity;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Entity\OutboundTransmissionEntity;
use App\System\ElectronicInvoicing\Infrastructure\Persistence\Doctrine\Mapper\OutboundTransmissionMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Transmissions and their append-only lifecycle events: a saved transmission
 * adds the statuses it did not have yet, never rewrites the others.
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

        $this->em->flush();
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
