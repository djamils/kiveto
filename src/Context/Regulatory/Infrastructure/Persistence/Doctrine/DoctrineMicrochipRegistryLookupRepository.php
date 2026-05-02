<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Infrastructure\Persistence\Doctrine;

use App\Context\Regulatory\Domain\MicrochipRegistryLookup;
use App\Context\Regulatory\Domain\Repository\MicrochipRegistryLookupRepositoryInterface;
use App\Context\Regulatory\Domain\ValueObject\MicrochipRegistryLookupId;
use App\Context\Regulatory\Infrastructure\Persistence\Doctrine\Entity\MicrochipRegistryLookupEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineMicrochipRegistryLookupRepository implements MicrochipRegistryLookupRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MicrochipRegistryLookupMapper $mapper,
    ) {
    }

    public function save(MicrochipRegistryLookup $lookup): void
    {
        $uuid       = Uuid::fromString($lookup->id()->value());
        $repository = $this->entityManager->getRepository(MicrochipRegistryLookupEntity::class);
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

    public function findById(MicrochipRegistryLookupId $id): ?MicrochipRegistryLookup
    {
        $uuid       = Uuid::fromString($id->value());
        $repository = $this->entityManager->getRepository(MicrochipRegistryLookupEntity::class);

        $entity = $repository->find($uuid);

        if (null === $entity) {
            return null;
        }

        return $this->mapper->toDomain($entity);
    }
}
