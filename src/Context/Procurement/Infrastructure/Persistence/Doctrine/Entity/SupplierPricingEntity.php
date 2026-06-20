<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Table name overridden because "pricing" → "pricings" would not match the spec.
 */
#[ORM\Entity]
#[ORM\Table(name: 'procurement__supplier_pricing')]
#[ORM\UniqueConstraint(name: 'uniq_sp_clinic_entry', columns: ['clinic_id', 'supplier_catalog_entry_id'])]
class SupplierPricingEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'clinic_id', type: UuidType::NAME)]
    private Uuid $clinicId;

    #[ORM\Column(name: 'supplier_id', type: UuidType::NAME)]
    private Uuid $supplierId;

    #[ORM\Column(name: 'supplier_catalog_entry_id', type: UuidType::NAME)]
    private Uuid $catalogEntryId;

    #[ORM\Column(name: 'amount_minor', type: 'integer')]
    private int $amountMinor;

    #[ORM\Column(name: 'amount_currency', type: 'string', length: 3)]
    private string $amountCurrency;

    #[ORM\Column(name: 'discount_percentage', type: 'string', length: 16, nullable: true)]
    private ?string $discountPercentage;

    #[ORM\Column(name: 'pricing_notes', type: 'text', nullable: true)]
    private ?string $pricingNotes;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'negotiated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $negotiatedAt;

    #[ORM\Version]
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    private int $version = 1;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getClinicId(): Uuid
    {
        return $this->clinicId;
    }

    public function setClinicId(Uuid $clinicId): void
    {
        $this->clinicId = $clinicId;
    }

    public function getSupplierId(): Uuid
    {
        return $this->supplierId;
    }

    public function setSupplierId(Uuid $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getCatalogEntryId(): Uuid
    {
        return $this->catalogEntryId;
    }

    public function setCatalogEntryId(Uuid $catalogEntryId): void
    {
        $this->catalogEntryId = $catalogEntryId;
    }

    public function getAmountMinor(): int
    {
        return $this->amountMinor;
    }

    public function setAmountMinor(int $amountMinor): void
    {
        $this->amountMinor = $amountMinor;
    }

    public function getAmountCurrency(): string
    {
        return $this->amountCurrency;
    }

    public function setAmountCurrency(string $amountCurrency): void
    {
        $this->amountCurrency = $amountCurrency;
    }

    public function getDiscountPercentage(): ?string
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(?string $discountPercentage): void
    {
        $this->discountPercentage = $discountPercentage;
    }

    public function getPricingNotes(): ?string
    {
        return $this->pricingNotes;
    }

    public function setPricingNotes(?string $pricingNotes): void
    {
        $this->pricingNotes = $pricingNotes;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
    }

    public function getNegotiatedAt(): \DateTimeImmutable
    {
        return $this->negotiatedAt;
    }

    public function setNegotiatedAt(\DateTimeImmutable $negotiatedAt): void
    {
        $this->negotiatedAt = $negotiatedAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
