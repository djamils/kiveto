<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Domain\ICADLookup;
use App\Context\Regulatory\Domain\Repository\ICADLookupRepositoryInterface;
use App\Context\Regulatory\Domain\ValueObject\ICADLookupId;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\ICADLookupEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineICADLookupRepository implements ICADLookupRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ICADLookupMapper $mapper,
    ) {
    }

    public function save(ICADLookup $lookup): void
    {
        $uuid       = Uuid::fromString($lookup->id()->value());
        $repository = $this->entityManager->getRepository(ICADLookupEntity::class);
        $entity     = $repository->find($uuid);

        if (null === $entity) {
            $entity = $this->mapper->toEntity($lookup);
            $this->entityManager->persist($entity);
        } else {
            $entity->setStatus($lookup->status());
            $entity->setIcadAnimalData($lookup->icadAnimalData());
            $entity->setErrorMessage($lookup->errorMessage());
            $entity->setUpdatedAt($lookup->updatedAt());
        }

        $this->entityManager->flush();
    }

    public function findById(ICADLookupId $id): ?ICADLookup
    {
        $uuid       = Uuid::fromString($id->value());
        $repository = $this->entityManager->getRepository(ICADLookupEntity::class);

        $entity = $repository->find($uuid);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
