<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Repository\ClientCommunicationRepositoryInterface;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientCommunicationEntity;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientCommunicationMapper;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineClientCommunicationRepository implements ClientCommunicationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClientCommunicationMapper $mapper,
    ) {
    }

    public function save(ClientCommunication $communication): void
    {
        $entity = $this->em->find(ClientCommunicationEntity::class, Uuid::fromString($communication->id()->toString()));

        // Manual entries are append-only; an automated one only changes its status.
        if (null === $entity) {
            $this->em->persist($this->mapper->toEntity($communication));
        } else {
            $this->mapper->updateEntity($entity, $communication);
        }

        $this->em->flush();
    }

    public function addIfAbsent(ClientCommunication $automatedCommunication): bool
    {
        $entity = $this->mapper->toEntity($automatedCommunication);

        // A no-op update on the unique key: a duplicate inserts nothing and
        // reports 0 affected rows instead of failing the transaction.
        return 1 === (int) $this->em->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO client__client_communications
                    (id, clinic_id, client_id, channel, direction, subject, body, author_name, occurred_at,
                     origin, delivery_status, notification_message_id, delivery_status_sequence, status_changed_at)
                VALUES
                    (:id, :clinicId, :clientId, :channel, :direction, :subject, :body, :authorName, :occurredAt,
                     :origin, :deliveryStatus, :notificationMessageId, :sequence, :statusChangedAt)
                ON DUPLICATE KEY UPDATE id = id
                SQL,
            [
                'id'                    => $entity->id->toBinary(),
                'clinicId'              => $entity->clinicId->toBinary(),
                'clientId'              => $entity->clientId->toBinary(),
                'channel'               => $entity->channel->value,
                'direction'             => $entity->direction->value,
                'subject'               => $entity->subject,
                'body'                  => $entity->body,
                'authorName'            => $entity->authorName,
                'occurredAt'            => $entity->occurredAt->format('Y-m-d H:i:s'),
                'origin'                => $entity->origin->value,
                'deliveryStatus'        => $entity->deliveryStatus?->value,
                'notificationMessageId' => $entity->notificationMessageId?->toBinary(),
                'sequence'              => $entity->deliveryStatusSequence,
                'statusChangedAt'       => $entity->statusChangedAt?->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function findByNotificationMessageId(string $notificationMessageId): ?ClientCommunication
    {
        return $this->findByMessage($notificationMessageId, null);
    }

    public function findByNotificationMessageIdForUpdate(string $notificationMessageId): ?ClientCommunication
    {
        return $this->findByMessage($notificationMessageId, LockMode::PESSIMISTIC_WRITE);
    }

    private function findByMessage(string $notificationMessageId, ?LockMode $lockMode): ?ClientCommunication
    {
        $query = $this->em->createQueryBuilder()
            ->select('c')
            ->from(ClientCommunicationEntity::class, 'c')
            ->where('c.notificationMessageId = :id')
            ->setParameter('id', Uuid::fromString($notificationMessageId), 'uuid')
            ->getQuery()
            // A long-running worker may hold an older copy of the row.
            ->setHint(Query::HINT_REFRESH, true)
        ;

        if (null !== $lockMode) {
            $query->setLockMode($lockMode);
        }

        $entity = $query->getOneOrNullResult();
        \assert(null === $entity || $entity instanceof ClientCommunicationEntity);

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }
}
