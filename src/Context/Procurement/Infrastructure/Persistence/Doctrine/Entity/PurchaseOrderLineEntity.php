<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: procurement__purchase_order_lines.
 */
#[ORM\Entity]
#[ORM\Table]
class PurchaseOrderLineEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(name: 'article_id', type: UuidType::NAME)]
    private Uuid $articleId;

    #[ORM\Column(name: 'catalog_entry_id', type: UuidType::NAME)]
    private Uuid $catalogEntryId;

    #[ORM\Column(name: 'ordered_amount', type: 'string', length: 32)]
    private string $orderedAmount;

    #[ORM\Column(name: 'ordered_unit', type: 'string', length: 32)]
    private string $orderedUnit;

    #[ORM\Column(name: 'unit_price_minor', type: 'integer')]
    private int $unitPriceMinor;

    #[ORM\Column(name: 'unit_price_currency', type: 'string', length: 3)]
    private string $unitPriceCurrency;

    #[ORM\Column(name: 'received_amount', type: 'string', length: 32, options: ['default' => '0'])]
    private string $receivedAmount;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'ACTIVE'])]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note;

    #[ORM\ManyToOne(targetEntity: PurchaseOrderEntity::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(name: 'purchase_order_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: false)]
    private PurchaseOrderEntity $purchaseOrder;

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(Uuid $id): void
    {
        $this->id = $id;
    }

    public function getArticleId(): Uuid
    {
        return $this->articleId;
    }

    public function setArticleId(Uuid $articleId): void
    {
        $this->articleId = $articleId;
    }

    public function getCatalogEntryId(): Uuid
    {
        return $this->catalogEntryId;
    }

    public function setCatalogEntryId(Uuid $catalogEntryId): void
    {
        $this->catalogEntryId = $catalogEntryId;
    }

    public function getOrderedAmount(): string
    {
        return $this->orderedAmount;
    }

    public function setOrderedAmount(string $orderedAmount): void
    {
        $this->orderedAmount = $orderedAmount;
    }

    public function getOrderedUnit(): string
    {
        return $this->orderedUnit;
    }

    public function setOrderedUnit(string $orderedUnit): void
    {
        $this->orderedUnit = $orderedUnit;
    }

    public function getUnitPriceMinor(): int
    {
        return $this->unitPriceMinor;
    }

    public function setUnitPriceMinor(int $unitPriceMinor): void
    {
        $this->unitPriceMinor = $unitPriceMinor;
    }

    public function getUnitPriceCurrency(): string
    {
        return $this->unitPriceCurrency;
    }

    public function setUnitPriceCurrency(string $unitPriceCurrency): void
    {
        $this->unitPriceCurrency = $unitPriceCurrency;
    }

    public function getReceivedAmount(): string
    {
        return $this->receivedAmount;
    }

    public function setReceivedAmount(string $receivedAmount): void
    {
        $this->receivedAmount = $receivedAmount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getPurchaseOrder(): PurchaseOrderEntity
    {
        return $this->purchaseOrder;
    }

    public function setPurchaseOrder(PurchaseOrderEntity $purchaseOrder): void
    {
        $this->purchaseOrder = $purchaseOrder;
    }
}
