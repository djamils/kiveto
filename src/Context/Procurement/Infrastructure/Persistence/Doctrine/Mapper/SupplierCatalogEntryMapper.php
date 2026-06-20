<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\Gtin;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryStatus;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierCatalogEntryEntity;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Uid\Uuid;

final readonly class SupplierCatalogEntryMapper
{
    public function toEntity(SupplierCatalogEntry $entry): SupplierCatalogEntryEntity
    {
        $entity = new SupplierCatalogEntryEntity();

        $entity->setId(Uuid::fromString($entry->id()->toString()));
        $entity->setSupplierId(Uuid::fromString($entry->supplierId()->toString()));
        $entity->setProductCode($entry->productCode()->toString());
        $entity->setName($entry->name()->toString());
        $entity->setGtin($entry->gtin()?->toString());
        $entity->setCatalogPriceMinor($entry->catalogPrice()->amount->minorUnits());
        $entity->setCatalogPriceCurrency($entry->catalogPrice()->amount->currency()->toString());
        $entity->setCatalogPriceValidFrom($entry->catalogPrice()->validFrom);
        $entity->setCatalogPriceValidTo($entry->catalogPrice()->validTo);
        $entity->setPackagingUnit($entry->packagingUnit()?->toString());
        $entity->setPackagingAmount($entry->packagingAmount());
        $entity->setStatus($entry->status()->value);
        $entity->setCreatedAt($entry->createdAt());
        $entity->setUpdatedAt($entry->updatedAt());

        return $entity;
    }

    public function toDomain(SupplierCatalogEntryEntity $entity): SupplierCatalogEntry
    {
        $gtin = null !== $entity->getGtin() ? Gtin::fromString($entity->getGtin()) : null;

        $catalogPrice = CatalogPrice::create(
            Money::fromMinorUnits(
                $entity->getCatalogPriceMinor(),
                CurrencyCode::fromString($entity->getCatalogPriceCurrency()),
            ),
            $entity->getCatalogPriceValidFrom(),
            $entity->getCatalogPriceValidTo(),
        );

        $packagingUnit = null !== $entity->getPackagingUnit()
            ? UnitOfMeasure::fromString($entity->getPackagingUnit())
            : null;

        return SupplierCatalogEntry::reconstitute(
            id: SupplierCatalogEntryId::fromString($entity->getId()->toString()),
            supplierId: SupplierId::fromString($entity->getSupplierId()->toString()),
            productCode: SupplierProductCode::fromString($entity->getProductCode()),
            name: SupplierProductName::fromString($entity->getName()),
            gtin: $gtin,
            catalogPrice: $catalogPrice,
            packagingUnit: $packagingUnit,
            packagingAmount: $entity->getPackagingAmount(),
            status: SupplierCatalogEntryStatus::from($entity->getStatus()),
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
