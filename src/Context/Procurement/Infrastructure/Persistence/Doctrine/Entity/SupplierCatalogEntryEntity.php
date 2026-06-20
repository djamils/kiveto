<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: procurement__supplier_catalog_entries.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uniq_sce_supplier_code', columns: ['supplier_id', 'supplier_product_code'])]
#[ORM\Index(name: 'idx_sce_name_fulltext', columns: ['name'], flags: ['fulltext'])]
class SupplierCatalogEntryEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'supplier_id', type: UuidType::NAME)]
    private Uuid $supplierId;

    #[ORM\Column(name: 'supplier_product_code', type: 'string', length: 128)]
    private string $productCode;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 14, nullable: true)]
    private ?string $gtin;

    #[ORM\Column(name: 'catalog_price_minor', type: 'integer')]
    private int $catalogPriceMinor;

    #[ORM\Column(name: 'catalog_price_currency', type: 'string', length: 3)]
    private string $catalogPriceCurrency;

    #[ORM\Column(name: 'catalog_price_valid_from', type: 'datetime_immutable')]
    private \DateTimeImmutable $catalogPriceValidFrom;

    #[ORM\Column(name: 'catalog_price_valid_to', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $catalogPriceValidTo;

    #[ORM\Column(name: 'packaging_unit', type: 'string', length: 32, nullable: true)]
    private ?string $packagingUnit;

    #[ORM\Column(name: 'packaging_amount', type: 'string', length: 32, nullable: true)]
    private ?string $packagingAmount;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status;

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

    public function getSupplierId(): Uuid
    {
        return $this->supplierId;
    }

    public function setSupplierId(Uuid $supplierId): void
    {
        $this->supplierId = $supplierId;
    }

    public function getProductCode(): string
    {
        return $this->productCode;
    }

    public function setProductCode(string $productCode): void
    {
        $this->productCode = $productCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    public function setGtin(?string $gtin): void
    {
        $this->gtin = $gtin;
    }

    public function getCatalogPriceMinor(): int
    {
        return $this->catalogPriceMinor;
    }

    public function setCatalogPriceMinor(int $catalogPriceMinor): void
    {
        $this->catalogPriceMinor = $catalogPriceMinor;
    }

    public function getCatalogPriceCurrency(): string
    {
        return $this->catalogPriceCurrency;
    }

    public function setCatalogPriceCurrency(string $catalogPriceCurrency): void
    {
        $this->catalogPriceCurrency = $catalogPriceCurrency;
    }

    public function getCatalogPriceValidFrom(): \DateTimeImmutable
    {
        return $this->catalogPriceValidFrom;
    }

    public function setCatalogPriceValidFrom(\DateTimeImmutable $catalogPriceValidFrom): void
    {
        $this->catalogPriceValidFrom = $catalogPriceValidFrom;
    }

    public function getCatalogPriceValidTo(): ?\DateTimeImmutable
    {
        return $this->catalogPriceValidTo;
    }

    public function setCatalogPriceValidTo(?\DateTimeImmutable $catalogPriceValidTo): void
    {
        $this->catalogPriceValidTo = $catalogPriceValidTo;
    }

    public function getPackagingUnit(): ?string
    {
        return $this->packagingUnit;
    }

    public function setPackagingUnit(?string $packagingUnit): void
    {
        $this->packagingUnit = $packagingUnit;
    }

    public function getPackagingAmount(): ?string
    {
        return $this->packagingAmount;
    }

    public function setPackagingAmount(?string $packagingAmount): void
    {
        $this->packagingAmount = $packagingAmount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
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
