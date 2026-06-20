<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierCatalogEntryEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierCatalogEntryMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineSupplierCatalogEntryRepository implements SupplierCatalogEntryRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private SupplierCatalogEntryMapper $mapper,
    ) {
    }

    public function save(SupplierCatalogEntry $entry): void
    {
        $entity = $this->mapper->toEntity($entry);
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findById(SupplierCatalogEntryId $id): ?SupplierCatalogEntry
    {
        $entity = $this->em->find(SupplierCatalogEntryEntity::class, Uuid::fromString($id->toString()));

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }

    public function findBySupplierAndCode(SupplierId $supplierId, SupplierProductCode $code): ?SupplierCatalogEntry
    {
        $entity = $this->em->getRepository(SupplierCatalogEntryEntity::class)->findOneBy([
            'supplierId'  => Uuid::fromString($supplierId->toString()),
            'productCode' => $code->toString(),
        ]);

        return null !== $entity ? $this->mapper->toDomain($entity) : null;
    }
}
