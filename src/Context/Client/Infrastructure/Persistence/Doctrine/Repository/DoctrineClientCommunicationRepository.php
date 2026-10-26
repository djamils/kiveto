<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Repository\ClientCommunicationRepositoryInterface;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Entity\ClientCommunicationEntity;
use App\Context\Client\Infrastructure\Persistence\Doctrine\Mapper\ClientCommunicationMapper;
use Doctrine\ORM\EntityManagerInterface;
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

    public function findByNotificationMessageId(string $notificationMessageId): ?ClientCommunication
    {
        $entity = $this->em->getRepository(ClientCommunicationEntity::class)->findOneBy([
            'notificationMessageId' => Uuid::fromString($notificationMessageId),
        ]);

        return null === $entity ? null : $this->mapper->toDomain($entity);
    }
}
