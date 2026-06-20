<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierPricing\Repository\SupplierPricingRepositoryInterface;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierPricingEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierPricingMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierPricingRepository implements SupplierPricingRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private SupplierPricingMapper $mapper,
    ) {
    }

    public function save(SupplierPricing $pricing): void
    {
        $entity = $this->mapper->toEntity($pricing);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(SupplierPricingId $id): ?SupplierPricing
    {
        $entity = $this->em->find(SupplierPricingEntity::class, Uuid::fromString($id->toString()));

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findByClinicAndEntry(ClinicId $clinicId, SupplierCatalogEntryId $entryId): ?SupplierPricing
    {
        $entity = $this->em->getRepository(SupplierPricingEntity::class)->findOneBy([
            'clinicId'       => Uuid::fromString($clinicId->toString()),
            'catalogEntryId' => Uuid::fromString($entryId->toString()),
        ]);

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function remove(SupplierPricing $pricing): void
    {
        $entity = $this->em->find(SupplierPricingEntity::class, Uuid::fromString($pricing->id()->toString()));

        if (null !== $entity) {
            $this->em->remove($entity);
            $this->em->flush();
        }
    }
}
