<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_article_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_article_status', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'uniq_article_code_tenant', columns: ['code', 'tenant_id'])]
class ArticleEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'tenant_id', type: UuidType::NAME)]
    private Uuid $tenantId;

    #[ORM\Column(type: 'string', length: 150)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20)]
    private string $code;

    #[ORM\Column(type: 'string', length: 20, enumType: ArticleKind::class)]
    private ArticleKind $kind;

    #[ORM\Column(type: 'string', length: 14, nullable: true)]
    private ?string $gtin = null;

    #[ORM\Column(name: 'tax_category_code', type: 'string', length: 50)]
    private string $taxCategoryCode;

    #[ORM\Column(name: 'base_price_minor_units', type: 'integer')]
    private int $basePriceMinorUnits;

    #[ORM\Column(name: 'base_price_currency', type: 'string', length: 3)]
    private string $basePriceCurrency;

    #[ORM\Column(name: 'unit_of_measure', type: 'string', length: 20)]
    private string $unitOfMeasure;

    #[ORM\Column(name: 'drug_auth_ref', type: UuidType::NAME, nullable: true)]
    private ?Uuid $drugAuthRef = null;

    #[ORM\Column(name: 'drug_requires_rx', type: 'boolean', nullable: true)]
    private ?bool $drugRequiresRx = null;

    #[ORM\Column(name: 'drug_prescription_class', type: 'string', length: 20, nullable: true)]
    private ?string $drugPrescriptionClass = null;

    #[ORM\Column(name: 'drug_is_controlled', type: 'boolean', nullable: true)]
    private ?bool $drugIsControlled = null;

    #[ORM\Column(name: 'track_stock', type: 'boolean')]
    private bool $trackStock;

    #[ORM\Column(type: 'string', length: 20, enumType: ArticleStatus::class)]
    private ArticleStatus $status;

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

    public function getTenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function setTenantId(Uuid $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getKind(): ArticleKind
    {
        return $this->kind;
    }

    public function setKind(ArticleKind $kind): void
    {
        $this->kind = $kind;
    }

    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    public function setGtin(?string $gtin): void
    {
        $this->gtin = $gtin;
    }

    public function getTaxCategoryCode(): string
    {
        return $this->taxCategoryCode;
    }

    public function setTaxCategoryCode(string $taxCategoryCode): void
    {
        $this->taxCategoryCode = $taxCategoryCode;
    }

    public function getBasePriceMinorUnits(): int
    {
        return $this->basePriceMinorUnits;
    }

    public function setBasePriceMinorUnits(int $basePriceMinorUnits): void
    {
        $this->basePriceMinorUnits = $basePriceMinorUnits;
    }

    public function getBasePriceCurrency(): string
    {
        return $this->basePriceCurrency;
    }

    public function setBasePriceCurrency(string $basePriceCurrency): void
    {
        $this->basePriceCurrency = $basePriceCurrency;
    }

    public function getUnitOfMeasure(): string
    {
        return $this->unitOfMeasure;
    }

    public function setUnitOfMeasure(string $unitOfMeasure): void
    {
        $this->unitOfMeasure = $unitOfMeasure;
    }

    public function getDrugAuthRef(): ?Uuid
    {
        return $this->drugAuthRef;
    }

    public function setDrugAuthRef(?Uuid $drugAuthRef): void
    {
        $this->drugAuthRef = $drugAuthRef;
    }

    public function getDrugRequiresRx(): ?bool
    {
        return $this->drugRequiresRx;
    }

    public function setDrugRequiresRx(?bool $drugRequiresRx): void
    {
        $this->drugRequiresRx = $drugRequiresRx;
    }

    public function getDrugPrescriptionClass(): ?string
    {
        return $this->drugPrescriptionClass;
    }

    public function setDrugPrescriptionClass(?string $drugPrescriptionClass): void
    {
        $this->drugPrescriptionClass = $drugPrescriptionClass;
    }

    public function getDrugIsControlled(): ?bool
    {
        return $this->drugIsControlled;
    }

    public function setDrugIsControlled(?bool $drugIsControlled): void
    {
        $this->drugIsControlled = $drugIsControlled;
    }

    public function getTrackStock(): bool
    {
        return $this->trackStock;
    }

    public function setTrackStock(bool $trackStock): void
    {
        $this->trackStock = $trackStock;
    }

    public function getStatus(): ArticleStatus
    {
        return $this->status;
    }

    public function setStatus(ArticleStatus $status): void
    {
        $this->status = $status;
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
