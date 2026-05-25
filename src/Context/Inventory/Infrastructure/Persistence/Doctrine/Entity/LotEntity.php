<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * BoundedContextPrefixNamingStrategy generates table name: inventory__lots.
 */
#[ORM\Entity]
#[ORM\Table]
#[ORM\UniqueConstraint(name: 'uq_lot_stock_item_number', columns: ['stock_item_id', 'lot_number'])]
#[ORM\Index(name: 'idx_lot_stock_item', columns: ['stock_item_id'])]
#[ORM\Index(name: 'idx_lots_active_expiry', columns: ['status', 'expiry_date'])]
#[ORM\Index(name: 'idx_lot_stock_status', columns: ['stock_item_id', 'status'])]
class LotEntity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: StockItemEntity::class, inversedBy: 'lots')]
    #[ORM\JoinColumn(name: 'stock_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private StockItemEntity $stockItem;

    #[ORM\Column(name: 'lot_number', type: 'string', length: 64)]
    private string $lotNumber;

    #[ORM\Column(name: 'quantity_amount', type: 'string', length: 32)]
    private string $quantityAmount;

    #[ORM\Column(name: 'quantity_unit', type: 'string', length: 16)]
    private string $quantityUnit;

    #[ORM\Column(name: 'expiry_date', type: 'date_immutable')]
    private \DateTimeImmutable $expiryDate;

    #[ORM\Column(name: 'received_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $receivedAt;

    #[ORM\Column(name: 'source_supplier', type: UuidType::NAME, nullable: true)]
    private ?Uuid $sourceSupplier;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => 'ACTIVE'])]
    private string $status;

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

    public function getStockItem(): StockItemEntity
    {
        return $this->stockItem;
    }

    public function setStockItem(StockItemEntity $stockItem): void
    {
        $this->stockItem = $stockItem;
    }

    public function getLotNumber(): string
    {
        return $this->lotNumber;
    }

    public function setLotNumber(string $lotNumber): void
    {
        $this->lotNumber = $lotNumber;
    }

    public function getQuantityAmount(): string
    {
        return $this->quantityAmount;
    }

    public function setQuantityAmount(string $quantityAmount): void
    {
        $this->quantityAmount = $quantityAmount;
    }

    public function getQuantityUnit(): string
    {
        return $this->quantityUnit;
    }

    public function setQuantityUnit(string $quantityUnit): void
    {
        $this->quantityUnit = $quantityUnit;
    }

    public function getExpiryDate(): \DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function setExpiryDate(\DateTimeImmutable $expiryDate): void
    {
        $this->expiryDate = $expiryDate;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(?\DateTimeImmutable $receivedAt): void
    {
        $this->receivedAt = $receivedAt;
    }

    public function getSourceSupplier(): ?Uuid
    {
        return $this->sourceSupplier;
    }

    public function setSourceSupplier(?Uuid $sourceSupplier): void
    {
        $this->sourceSupplier = $sourceSupplier;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
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
