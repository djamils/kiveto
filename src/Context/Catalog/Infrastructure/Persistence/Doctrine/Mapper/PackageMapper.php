<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Mapper;

use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\PackageComponent;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageComponentId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\Quantity;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PackageComponentEntity;
use App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity\PackageEntity;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use Symfony\Component\Uid\Uuid;

final readonly class PackageMapper
{
    /**
     * @param list<PackageComponentEntity> $componentEntities
     */
    public function toEntity(Package $package, array $componentEntities, ?PackageEntity $existing = null): PackageEntity
    {
        $entity = $existing ?? new PackageEntity();

        $entity->setId(Uuid::fromString($package->id()->toString()));
        $entity->setTenantId(Uuid::fromString($package->clinicId()->toString()));
        $entity->setName($package->name()->toString());
        $entity->setCode($package->code()->toString());
        $entity->setTaxCategoryCode($package->taxCategory()->toString());
        $entity->setPricingMode($package->pricingMode());
        $entity->setStatus($package->status());
        $entity->setCreatedAt($package->createdAt());
        $entity->setUpdatedAt($package->updatedAt());

        $fixedPrice = $package->fixedPrice();

        if (null !== $fixedPrice) {
            $entity->setFixedPriceMinorUnits($fixedPrice->minorUnits());
            $entity->setFixedPriceCurrency($fixedPrice->currency()->toString());
        } else {
            $entity->setFixedPriceMinorUnits(null);
            $entity->setFixedPriceCurrency(null);
        }

        return $entity;
    }

    /**
     * @param list<PackageComponentEntity> $componentEntities
     */
    public function toDomain(PackageEntity $entity, array $componentEntities): Package
    {
        $components = array_map(
            static fn (PackageComponentEntity $c) => PackageComponent::create(
                id: PackageComponentId::fromString($c->getId()->toString()),
                itemRef: CatalogItemRef::fromPrimitives($c->getItemType(), $c->getItemId()),
                quantity: Quantity::of($c->getQuantity()),
                weight: $c->getWeight(),
            ),
            $componentEntities,
        );

        $fixedPrice = null;

        if (null !== $entity->getFixedPriceMinorUnits() && null !== $entity->getFixedPriceCurrency()) {
            $fixedPrice = Money::fromMinorUnits(
                $entity->getFixedPriceMinorUnits(),
                CurrencyCode::fromString($entity->getFixedPriceCurrency()),
            );
        }

        return Package::reconstitute(
            id: PackageId::fromString($entity->getId()->toString()),
            clinicId: ClinicId::fromString($entity->getTenantId()->toString()),
            name: PackageName::fromString($entity->getName()),
            code: PackageCode::fromString($entity->getCode()),
            taxCategory: TaxCategoryCode::fromString($entity->getTaxCategoryCode()),
            pricingMode: $entity->getPricingMode(),
            fixedPrice: $fixedPrice,
            components: $components,
            status: $entity->getStatus(),
            createdAt: $entity->getCreatedAt(),
            updatedAt: $entity->getUpdatedAt(),
        );
    }
}
