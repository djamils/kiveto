<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\WaitingRoomEntry;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Entity\WaitingRoomEntryEntity;
use App\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\WaitingRoomEntryMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineWaitingRoomEntryRepository implements WaitingRoomEntryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private WaitingRoomEntryMapper $mapper,
    ) {
    }

    public function save(WaitingRoomEntry $entry): void
    {
        $repository = $this->entityManager->getRepository(WaitingRoomEntryEntity::class);
        $entity     = $repository->find(Uuid::fromString($entry->id()->toString()));

        if (null === $entity) {
            $entity = $this->mapper->toEntity($entry);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($entry, $entity);
        }

        $this->entityManager->flush();
    }

    public function findById(WaitingRoomEntryId $id): ?WaitingRoomEntry
    {
        $repository = $this->entityManager->getRepository(WaitingRoomEntryEntity::class);
        $entity     = $repository->find(Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
