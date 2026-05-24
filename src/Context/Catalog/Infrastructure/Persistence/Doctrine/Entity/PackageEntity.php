<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use App\Context\Catalog\Domain\Package\ValueObject\PackagePricingMode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_package_tenant', columns: ['tenant_id'])]
#[ORM\Index(name: 'idx_package_status', columns: ['status'])]
#[ORM\UniqueConstraint(name: 'uniq_package_code_tenant', columns: ['code', 'tenant_id'])]
class PackageEntity
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

    #[ORM\Column(name: 'tax_category_code', type: 'string', length: 50)]
    private string $taxCategoryCode;

    #[ORM\Column(name: 'pricing_mode', type: 'string', length: 20, enumType: PackagePricingMode::class)]
    private PackagePricingMode $pricingMode;

    #[ORM\Column(name: 'fixed_price_minor_units', type: 'integer', nullable: true)]
    private ?int $fixedPriceMinorUnits = null;

    #[ORM\Column(name: 'fixed_price_currency', type: 'string', length: 3, nullable: true)]
    private ?string $fixedPriceCurrency = null;

    #[ORM\Column(type: 'string', length: 20, enumType: PackageStatus::class)]
    private PackageStatus $status;

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

    public function getTaxCategoryCode(): string
    {
        return $this->taxCategoryCode;
    }

    public function setTaxCategoryCode(string $taxCategoryCode): void
    {
        $this->taxCategoryCode = $taxCategoryCode;
    }

    public function getPricingMode(): PackagePricingMode
    {
        return $this->pricingMode;
    }

    public function setPricingMode(PackagePricingMode $pricingMode): void
    {
        $this->pricingMode = $pricingMode;
    }

    public function getFixedPriceMinorUnits(): ?int
    {
        return $this->fixedPriceMinorUnits;
    }

    public function setFixedPriceMinorUnits(?int $fixedPriceMinorUnits): void
    {
        $this->fixedPriceMinorUnits = $fixedPriceMinorUnits;
    }

    public function getFixedPriceCurrency(): ?string
    {
        return $this->fixedPriceCurrency;
    }

    public function setFixedPriceCurrency(?string $fixedPriceCurrency): void
    {
        $this->fixedPriceCurrency = $fixedPriceCurrency;
    }

    public function getStatus(): PackageStatus
    {
        return $this->status;
    }

    public function setStatus(PackageStatus $status): void
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
