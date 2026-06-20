<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierPricing\SupplierPricing;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\NegotiatedPrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\SupplierPricingEntity;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Symfony\Component\Uid\Uuid;

final readonly class SupplierPricingMapper
{
    public function toEntity(SupplierPricing $pricing): SupplierPricingEntity
    {
        $entity = new SupplierPricingEntity();

        $entity->setId(Uuid::fromString($pricing->id()->toString()));
        $entity->setClinicId(Uuid::fromString($pricing->clinicId()->toString()));
        $entity->setSupplierId(Uuid::fromString($pricing->supplierId()->toString()));
        $entity->setCatalogEntryId(Uuid::fromString($pricing->catalogEntryId()->toString()));
        $entity->setAmountMinor($pricing->negotiatedPrice()->amount->minorUnits());
        $entity->setAmountCurrency($pricing->negotiatedPrice()->amount->currency()->toString());
        $entity->setDiscountPercentage($pricing->negotiatedPrice()->discountPercentage);
        $entity->setPricingNotes($pricing->negotiatedPrice()->notes);
        $entity->setExpiresAt($pricing->expiresAt());
        $entity->setNegotiatedAt($pricing->negotiatedAt());
        $entity->setCreatedAt($pricing->createdAt());
        $entity->setUpdatedAt($pricing->updatedAt());

        return $entity;
    }

    public function toDomain(SupplierPricingEntity $entity): SupplierPricing
    {
        $negotiatedPrice = NegotiatedPrice::create(
            Money::fromMinorUnits(
                $entity->getAmountMinor(),
                CurrencyCode::fromString($entity->getAmountCurrency()),
            ),
            $entity->getDiscountPercentage(),
            $entity->getPricingNotes(),
        );

        return SupplierPricing::reconstitute(
            id: SupplierPricingId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getClinicId()->toString()),
            supplierId: SupplierId::fromString($entity->getSupplierId()->toString()),
            catalogEntryId: SupplierCatalogEntryId::fromString($entity->getCatalogEntryId()->toString()),
            negotiatedPrice: $negotiatedPrice,
            expiresAt: $entity->getExpiresAt(),
            negotiatedAt: $entity->getNegotiatedAt(),
            version: $entity->getVersion(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
