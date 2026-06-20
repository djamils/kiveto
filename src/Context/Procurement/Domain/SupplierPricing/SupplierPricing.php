<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierPricing;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierPricing\Event\SupplierPricingNegotiated;
use App\Context\Procurement\Domain\SupplierPricing\Event\SupplierPricingRemoved;
use App\Context\Procurement\Domain\SupplierPricing\Event\SupplierPricingUpdated;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\NegotiatedPrice;
use App\Context\Procurement\Domain\SupplierPricing\ValueObject\SupplierPricingId;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class SupplierPricing extends AggregateRoot
{
    private bool $removed = false;

    private function __construct(
        private SupplierPricingId $id,
        private ClinicId $clinicId,
        private SupplierId $supplierId,
        private SupplierCatalogEntryId $catalogEntryId,
        private NegotiatedPrice $negotiatedPrice,
        private ?\DateTimeImmutable $expiresAt,
        private \DateTimeImmutable $negotiatedAt,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function negotiate(
        SupplierPricingId $id,
        ClinicId $clinicId,
        SupplierId $supplierId,
        SupplierCatalogEntryId $catalogEntryId,
        NegotiatedPrice $negotiatedPrice,
        ?\DateTimeImmutable $expiresAt,
        \DateTimeImmutable $negotiatedAt,
    ): self {
        if (null !== $expiresAt && $expiresAt <= $negotiatedAt) {
            throw new \InvalidArgumentException('expiresAt must be after negotiatedAt.');
        }

        $pricing = new self(
            id: $id,
            clinicId: $clinicId,
            supplierId: $supplierId,
            catalogEntryId: $catalogEntryId,
            negotiatedPrice: $negotiatedPrice,
            expiresAt: $expiresAt,
            negotiatedAt: $negotiatedAt,
            version: 1,
            createdAt: $negotiatedAt,
            updatedAt: $negotiatedAt,
        );

        $pricing->recordDomainEvent(new SupplierPricingNegotiated(
            pricingId: $id->toString(),
            clinicId: $clinicId->toString(),
            supplierId: $supplierId->toString(),
            catalogEntryId: $catalogEntryId->toString(),
            amountMinor: $negotiatedPrice->amount->minorUnits(),
            currency: $negotiatedPrice->amount->currency()->toString(),
        ));

        return $pricing;
    }

    public static function reconstitute(
        SupplierPricingId $id,
        ClinicId $clinicId,
        SupplierId $supplierId,
        SupplierCatalogEntryId $catalogEntryId,
        NegotiatedPrice $negotiatedPrice,
        ?\DateTimeImmutable $expiresAt,
        \DateTimeImmutable $negotiatedAt,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            supplierId: $supplierId,
            catalogEntryId: $catalogEntryId,
            negotiatedPrice: $negotiatedPrice,
            expiresAt: $expiresAt,
            negotiatedAt: $negotiatedAt,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function update(NegotiatedPrice $negotiatedPrice, ?\DateTimeImmutable $expiresAt, \DateTimeImmutable $updatedAt): void
    {
        $this->negotiatedPrice = $negotiatedPrice;
        $this->expiresAt       = $expiresAt;
        $this->updatedAt       = $updatedAt;

        $this->recordDomainEvent(new SupplierPricingUpdated(
            pricingId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            catalogEntryId: $this->catalogEntryId->toString(),
        ));
    }

    public function remove(\DateTimeImmutable $updatedAt): void
    {
        $this->removed   = true;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new SupplierPricingRemoved(
            pricingId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            catalogEntryId: $this->catalogEntryId->toString(),
        ));
    }

    public function isRemoved(): bool
    {
        return $this->removed;
    }

    public function id(): SupplierPricingId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function supplierId(): SupplierId
    {
        return $this->supplierId;
    }

    public function catalogEntryId(): SupplierCatalogEntryId
    {
        return $this->catalogEntryId;
    }

    public function negotiatedPrice(): NegotiatedPrice
    {
        return $this->negotiatedPrice;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function negotiatedAt(): \DateTimeImmutable
    {
        return $this->negotiatedAt;
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
