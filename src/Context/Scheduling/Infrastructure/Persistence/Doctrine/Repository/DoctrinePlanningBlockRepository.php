<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\PlanningBlock;
use App\Context\Scheduling\Domain\Repository\PlanningBlockRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockId;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Entity\PlanningBlockEntity;
use App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Mapper\PlanningBlockMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrinePlanningBlockRepository implements PlanningBlockRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PlanningBlockMapper $mapper,
    ) {
    }

    public function save(PlanningBlock $block): void
    {
        $repository = $this->entityManager->getRepository(PlanningBlockEntity::class);
        $entity     = $repository->find(Uuid::fromString($block->id()->toString()));

        if (null === $entity) {
            $entity = $this->mapper->toEntity($block);
            $this->entityManager->persist($entity);
        } else {
            $this->mapper->updateEntity($block, $entity);
        }

        $this->entityManager->flush();
    }

    public function findById(PlanningBlockId $id): ?PlanningBlock
    {
        $repository = $this->entityManager->getRepository(PlanningBlockEntity::class);
        $entity     = $repository->find(Uuid::fromString($id->toString()));

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }

    public function delete(PlanningBlock $block): void
    {
        $repository = $this->entityManager->getRepository(PlanningBlockEntity::class);
        $entity     = $repository->find(Uuid::fromString($block->id()->toString()));

        if (null !== $entity) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
