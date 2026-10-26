<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Index(name: 'idx_plitem_pricelist', columns: ['price_list_id'])]
class PriceListItemEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'price_list_id', type: UuidType::NAME)]
    private Uuid $priceListId;

    #[ORM\Column(name: 'item_type', type: 'string', length: 10)]
    private string $itemType;

    #[ORM\Column(name: 'item_id', type: 'string', length: 36)]
    private string $itemId;

    #[ORM\Column(name: 'price_minor_units', type: 'integer')]
    private int $priceMinorUnits;

    #[ORM\Column(name: 'price_currency', type: 'string', length: 3)]
    private string $priceCurrency;

    #[ORM\Column(name: 'price_mode', type: 'string', length: 4)]
    private string $priceMode;

    #[ORM\Column(name: 'tax_category_override', type: 'string', length: 50, nullable: true)]
    private ?string $taxCategoryOverride = null;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getPriceListId(): Uuid
    {
        return $this->priceListId;
    }

    public function setPriceListId(Uuid $priceListId): void
    {
        $this->priceListId = $priceListId;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): void
    {
        $this->itemType = $itemType;
    }

    public function getItemId(): string
    {
        return $this->itemId;
    }

    public function setItemId(string $itemId): void
    {
        $this->itemId = $itemId;
    }

    public function getPriceMinorUnits(): int
    {
        return $this->priceMinorUnits;
    }

    public function setPriceMinorUnits(int $priceMinorUnits): void
    {
        $this->priceMinorUnits = $priceMinorUnits;
    }

    public function getPriceCurrency(): string
    {
        return $this->priceCurrency;
    }

    public function setPriceCurrency(string $priceCurrency): void
    {
        $this->priceCurrency = $priceCurrency;
    }

    public function getPriceMode(): string
    {
        return $this->priceMode;
    }

    public function setPriceMode(string $priceMode): void
    {
        $this->priceMode = $priceMode;
    }

    public function getTaxCategoryOverride(): ?string
    {
        return $this->taxCategoryOverride;
    }

    public function setTaxCategoryOverride(?string $taxCategoryOverride): void
    {
        $this->taxCategoryOverride = $taxCategoryOverride;
    }
}
