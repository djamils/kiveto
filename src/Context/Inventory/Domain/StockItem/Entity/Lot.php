<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem\Entity;

use App\Context\Inventory\Domain\Shared\ValueObject\SupplierId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;

/**
 * Lot child entity — owned exclusively by StockItem.
 * All mutations are via StockItem methods; Lot exposes no public mutators.
 */
class Lot
{
    public function __construct(
        private readonly LotId $id,
        private readonly LotNumber $number,
        private Quantity $quantity,
        private readonly \DateTimeImmutable $expiryDate,
        private readonly ?\DateTimeImmutable $receivedAt,
        private readonly ?SupplierId $sourceSupplier,
        private LotStatus $status,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function id(): LotId
    {
        return $this->id;
    }

    public function number(): LotNumber
    {
        return $this->number;
    }

    public function quantity(): Quantity
    {
        return $this->quantity;
    }

    public function expiryDate(): \DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function receivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function sourceSupplier(): ?SupplierId
    {
        return $this->sourceSupplier;
    }

    public function status(): LotStatus
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

    /**
     * Internal — called only by StockItem::receiveStock() when the same lot number is received again.
     */
    public function incrementQuantity(Quantity $delta, \DateTimeImmutable $updatedAt): void
    {
        $this->quantity  = $this->quantity->add($delta);
        $this->updatedAt = $updatedAt;
    }

    /**
     * Internal — called only by StockItem::consumeStock() per FEFO traversal step.
     */
    public function decrementQuantity(Quantity $delta, \DateTimeImmutable $updatedAt): void
    {
        $this->quantity  = $this->quantity->subtract($delta);
        $this->updatedAt = $updatedAt;
    }

    /**
     * Internal — used by StockItem::adjustStock() and StockItem::markLotAsRecalled().
     */
    public function setQuantity(Quantity $quantity, \DateTimeImmutable $updatedAt): void
    {
        $this->quantity  = $quantity;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Internal — transitions lot to a new status.
     */
    public function markAs(LotStatus $status, \DateTimeImmutable $updatedAt): void
    {
        $this->status    = $status;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Internal — zeroes lot quantity (used for RECALLED lots).
     */
    public function setQuantityZero(\DateTimeImmutable $updatedAt): void
    {
        $this->quantity  = Quantity::zero($this->quantity->unit());
        $this->updatedAt = $updatedAt;
    }
}
