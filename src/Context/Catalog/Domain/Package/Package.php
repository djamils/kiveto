<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package;

use App\Context\Catalog\Domain\Package\Event\PackageArchived;
use App\Context\Catalog\Domain\Package\Event\PackageComponentAdded;
use App\Context\Catalog\Domain\Package\Event\PackageComponentRemoved;
use App\Context\Catalog\Domain\Package\Event\PackageCreated;
use App\Context\Catalog\Domain\Package\Event\PackageFixedPriceChanged;
use App\Context\Catalog\Domain\Package\Event\PackageRestored;
use App\Context\Catalog\Domain\Package\Exception\InvalidPackagePricingModeException;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageComponentId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageName;
use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageStatus;
use App\Context\Catalog\Domain\Package\ValueObject\Quantity;
use App\Context\Catalog\Domain\Shared\ValueObject\CatalogItemRef;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;

final class Package extends AggregateRoot
{
    /** @param list<PackageComponent> $components */
    private function __construct(
        private PackageId $id,
        private ClinicId $clinicId,
        private PackageName $name,
        private PackageCode $code,
        private TaxCategoryCode $taxCategory,
        private PackagePricingMode $pricingMode,
        private ?Money $fixedPrice,
        private array $components,
        private PackageStatus $status,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        PackageId $id,
        ClinicId $clinicId,
        PackageName $name,
        PackageCode $code,
        TaxCategoryCode $taxCategory,
        PackagePricingMode $pricingMode,
        ?Money $fixedPrice,
        \DateTimeImmutable $createdAt,
    ): self {
        if (PackagePricingMode::FIXED_PRICE === $pricingMode && null === $fixedPrice) {
            throw new InvalidPackagePricingModeException('FIXED_PRICE mode requires a fixedPrice.');
        }

        if (PackagePricingMode::SUM_OF_COMPONENTS === $pricingMode && null !== $fixedPrice) {
            throw new InvalidPackagePricingModeException('SUM_OF_COMPONENTS mode must not have a fixedPrice.');
        }

        $package = new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            code: $code,
            taxCategory: $taxCategory,
            pricingMode: $pricingMode,
            fixedPrice: $fixedPrice,
            components: [],
            status: PackageStatus::ACTIVE,
            createdAt: $createdAt,
            updatedAt: $createdAt,
        );

        $package->recordDomainEvent(new PackageCreated(
            packageId: $id->toString(),
            clinicId: $clinicId->toString(),
            name: $name->toString(),
            code: $code->toString(),
            pricingMode: $pricingMode->value,
        ));

        return $package;
    }

    /** @param list<PackageComponent> $components */
    public static function reconstitute(
        PackageId $id,
        ClinicId $clinicId,
        PackageName $name,
        PackageCode $code,
        TaxCategoryCode $taxCategory,
        PackagePricingMode $pricingMode,
        ?Money $fixedPrice,
        array $components,
        PackageStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            name: $name,
            code: $code,
            taxCategory: $taxCategory,
            pricingMode: $pricingMode,
            fixedPrice: $fixedPrice,
            components: $components,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function addComponent(CatalogItemRef $itemRef, Quantity $quantity, int $weight, \DateTimeImmutable $updatedAt): PackageComponentId
    {
        $componentId        = PackageComponentId::fromString(\Symfony\Component\Uid\Uuid::v7()->toRfc4122());
        $this->components[] = PackageComponent::create($componentId, $itemRef, $quantity, $weight);
        $this->updatedAt    = $updatedAt;

        $this->recordDomainEvent(new PackageComponentAdded(
            packageId: $this->id->toString(),
            componentId: $componentId->toString(),
            itemType: $itemRef->type()->value,
            itemId: $itemRef->id(),
            quantity: $quantity->value(),
            weight: $weight,
        ));

        return $componentId;
    }

    public function removeComponent(PackageComponentId $componentId, \DateTimeImmutable $updatedAt): void
    {
        foreach ($this->components as $key => $component) {
            if ($component->id()->equals($componentId)) {
                $remaining = $this->components;
                unset($remaining[$key]);
                /** @var list<PackageComponent> $reindexed */
                $reindexed        = array_values($remaining);
                $this->components = $reindexed;
                $this->updatedAt  = $updatedAt;

                $this->recordDomainEvent(new PackageComponentRemoved(
                    packageId: $this->id->toString(),
                    componentId: $componentId->toString(),
                ));

                return;
            }
        }
    }

    public function changeFixedPrice(Money $fixedPrice, \DateTimeImmutable $updatedAt): void
    {
        if (PackagePricingMode::FIXED_PRICE !== $this->pricingMode) {
            throw new InvalidPackagePricingModeException('Can only change fixed price on FIXED_PRICE mode packages.');
        }

        $this->fixedPrice = $fixedPrice;
        $this->updatedAt  = $updatedAt;

        $this->recordDomainEvent(new PackageFixedPriceChanged(
            packageId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            newPriceMinorUnits: $fixedPrice->minorUnits(),
            newPriceCurrency: $fixedPrice->currency()->toString(),
        ));
    }

    public function archive(\DateTimeImmutable $updatedAt): void
    {
        if (PackageStatus::ARCHIVED === $this->status) {
            return;
        }

        $this->status    = PackageStatus::ARCHIVED;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new PackageArchived(
            packageId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function restore(\DateTimeImmutable $updatedAt): void
    {
        if (PackageStatus::ACTIVE === $this->status) {
            return;
        }

        $this->status    = PackageStatus::ACTIVE;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new PackageRestored(
            packageId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function id(): PackageId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function name(): PackageName
    {
        return $this->name;
    }

    public function code(): PackageCode
    {
        return $this->code;
    }

    public function taxCategory(): TaxCategoryCode
    {
        return $this->taxCategory;
    }

    public function pricingMode(): PackagePricingMode
    {
        return $this->pricingMode;
    }

    public function fixedPrice(): ?Money
    {
        return $this->fixedPrice;
    }

    /** @return list<PackageComponent> */
    public function components(): array
    {
        return $this->components;
    }

    public function status(): PackageStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
