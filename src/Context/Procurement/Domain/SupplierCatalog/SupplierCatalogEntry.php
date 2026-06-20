<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierCatalog;

use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogEntryAdded;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogEntryDiscontinued;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogEntryUpdated;
use App\Context\Procurement\Domain\SupplierCatalog\Event\SupplierCatalogPriceChanged;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\Gtin;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryStatus;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;

final class SupplierCatalogEntry extends AggregateRoot
{
    private function __construct(
        private SupplierCatalogEntryId $id,
        private SupplierId $supplierId,
        private SupplierProductCode $productCode,
        private SupplierProductName $name,
        private ?Gtin $gtin,
        private CatalogPrice $catalogPrice,
        private ?UnitOfMeasure $packagingUnit,
        private ?string $packagingAmount,
        private SupplierCatalogEntryStatus $status,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function add(
        SupplierCatalogEntryId $id,
        SupplierId $supplierId,
        SupplierProductCode $productCode,
        SupplierProductName $name,
        ?Gtin $gtin,
        CatalogPrice $catalogPrice,
        ?UnitOfMeasure $packagingUnit = null,
        ?string $packagingAmount = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $now   = $createdAt ?? new \DateTimeImmutable();
        $entry = new self(
            id: $id,
            supplierId: $supplierId,
            productCode: $productCode,
            name: $name,
            gtin: $gtin,
            catalogPrice: $catalogPrice,
            packagingUnit: $packagingUnit,
            packagingAmount: $packagingAmount,
            status: SupplierCatalogEntryStatus::ACTIVE,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entry->recordDomainEvent(new SupplierCatalogEntryAdded(
            entryId: $id->toString(),
            supplierId: $supplierId->toString(),
            productCode: $productCode->toString(),
            name: $name->toString(),
        ));

        return $entry;
    }

    public static function reconstitute(
        SupplierCatalogEntryId $id,
        SupplierId $supplierId,
        SupplierProductCode $productCode,
        SupplierProductName $name,
        ?Gtin $gtin,
        CatalogPrice $catalogPrice,
        ?UnitOfMeasure $packagingUnit,
        ?string $packagingAmount,
        SupplierCatalogEntryStatus $status,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            supplierId: $supplierId,
            productCode: $productCode,
            name: $name,
            gtin: $gtin,
            catalogPrice: $catalogPrice,
            packagingUnit: $packagingUnit,
            packagingAmount: $packagingAmount,
            status: $status,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function update(
        SupplierProductName $name,
        ?Gtin $gtin,
        ?UnitOfMeasure $packagingUnit,
        ?string $packagingAmount,
        \DateTimeImmutable $updatedAt,
    ): void {
        $this->name            = $name;
        $this->gtin            = $gtin;
        $this->packagingUnit   = $packagingUnit;
        $this->packagingAmount = $packagingAmount;
        $this->updatedAt       = $updatedAt;

        $this->recordDomainEvent(new SupplierCatalogEntryUpdated(
            entryId: $this->id->toString(),
            supplierId: $this->supplierId->toString(),
        ));
    }

    public function updatePrice(CatalogPrice $newPrice, \DateTimeImmutable $updatedAt): void
    {
        $this->catalogPrice = $newPrice;
        $this->updatedAt    = $updatedAt;

        $this->recordDomainEvent(new SupplierCatalogPriceChanged(
            entryId: $this->id->toString(),
            supplierId: $this->supplierId->toString(),
            newPriceMinor: $newPrice->amount->minorUnits(),
            currency: $newPrice->amount->currency()->toString(),
        ));
    }

    public function discontinue(\DateTimeImmutable $updatedAt): void
    {
        $this->status    = SupplierCatalogEntryStatus::DISCONTINUED;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new SupplierCatalogEntryDiscontinued(
            entryId: $this->id->toString(),
            supplierId: $this->supplierId->toString(),
        ));
    }

    public function id(): SupplierCatalogEntryId
    {
        return $this->id;
    }

    public function supplierId(): SupplierId
    {
        return $this->supplierId;
    }

    public function productCode(): SupplierProductCode
    {
        return $this->productCode;
    }

    public function name(): SupplierProductName
    {
        return $this->name;
    }

    public function gtin(): ?Gtin
    {
        return $this->gtin;
    }

    public function catalogPrice(): CatalogPrice
    {
        return $this->catalogPrice;
    }

    public function packagingUnit(): ?UnitOfMeasure
    {
        return $this->packagingUnit;
    }

    public function packagingAmount(): ?string
    {
        return $this->packagingAmount;
    }

    public function status(): SupplierCatalogEntryStatus
    {
        return $this->status;
    }

    public function version(): int
    {
        return $this->version;
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
