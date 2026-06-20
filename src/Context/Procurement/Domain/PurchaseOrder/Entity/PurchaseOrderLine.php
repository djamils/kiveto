<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder\Entity;

use App\Context\Procurement\Application\Exception\CancelledLineCannotReceiveException;
use App\Context\Procurement\Application\Exception\ReceiptQuantityExceedsOrderedException;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineStatus;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Money\Domain\ValueObject\Money;

final class PurchaseOrderLine
{
    /** @phpstan-var numeric-string */
    private string $orderedAmount;

    /** @phpstan-var numeric-string */
    private string $receivedAmount;

    /**
     * @phpstan-param numeric-string $orderedAmount
     * @phpstan-param numeric-string $receivedAmount
     */
    private function __construct(
        private PurchaseOrderLineId $id,
        private ArticleId $articleId,
        private SupplierCatalogEntryId $catalogEntryId,
        string $orderedAmount,
        private UnitOfMeasure $orderedUnit,
        private Money $unitPrice,
        string $receivedAmount,
        private PurchaseOrderLineStatus $status,
        private ?string $note,
    ) {
        $this->orderedAmount  = $orderedAmount;
        $this->receivedAmount = $receivedAmount;
    }

    /**
     * @phpstan-param numeric-string $orderedAmount
     */
    public static function create(
        PurchaseOrderLineId $id,
        ArticleId $articleId,
        SupplierCatalogEntryId $catalogEntryId,
        string $orderedAmount,
        UnitOfMeasure $orderedUnit,
        Money $unitPrice,
        ?string $note = null,
    ): self {
        return new self(
            id: $id,
            articleId: $articleId,
            catalogEntryId: $catalogEntryId,
            orderedAmount: $orderedAmount,
            orderedUnit: $orderedUnit,
            unitPrice: $unitPrice,
            receivedAmount: '0',
            status: PurchaseOrderLineStatus::ACTIVE,
            note: $note,
        );
    }

    /**
     * @phpstan-param numeric-string $orderedAmount
     * @phpstan-param numeric-string $receivedAmount
     */
    public static function reconstitute(
        PurchaseOrderLineId $id,
        ArticleId $articleId,
        SupplierCatalogEntryId $catalogEntryId,
        string $orderedAmount,
        UnitOfMeasure $orderedUnit,
        Money $unitPrice,
        string $receivedAmount,
        PurchaseOrderLineStatus $status,
        ?string $note,
    ): self {
        return new self(
            id: $id,
            articleId: $articleId,
            catalogEntryId: $catalogEntryId,
            orderedAmount: $orderedAmount,
            orderedUnit: $orderedUnit,
            unitPrice: $unitPrice,
            receivedAmount: $receivedAmount,
            status: $status,
            note: $note,
        );
    }

    /**
     * Records a reception quantity for this line.
     *
     * @phpstan-param numeric-string $receivedAmount
     *
     * @throws CancelledLineCannotReceiveException    if line is CANCELLED
     * @throws ReceiptQuantityExceedsOrderedException if reception would exceed ordered quantity
     */
    public function recordReception(string $receivedAmount): void
    {
        if (PurchaseOrderLineStatus::CANCELLED === $this->status) {
            throw new CancelledLineCannotReceiveException($this->id->toString());
        }

        $newReceived = bcadd($this->receivedAmount, $receivedAmount, 6);
        if (bccomp($newReceived, $this->orderedAmount, 6) > 0) {
            throw new ReceiptQuantityExceedsOrderedException(
                lineId: $this->id->toString(),
                ordered: $this->orderedAmount,
                alreadyReceived: $this->receivedAmount,
                attempted: $receivedAmount,
            );
        }

        $this->receivedAmount = $newReceived;
        if (bccomp($this->receivedAmount, $this->orderedAmount, 6) >= 0) {
            $this->status = PurchaseOrderLineStatus::FULLY_RECEIVED;
        }
    }

    public function cancel(string $reason): void
    {
        $this->status = PurchaseOrderLineStatus::CANCELLED;
        $this->note   = $reason;
    }

    public function lineTotal(): Money
    {
        $totalMinor = (int) bcmul((string) $this->unitPrice->minorUnits(), $this->orderedAmount, 0);

        return Money::fromMinorUnits($totalMinor, $this->unitPrice->currency());
    }

    /**
     * @phpstan-param numeric-string $orderedAmount
     */
    public function update(string $orderedAmount, Money $unitPrice, ?string $note): void
    {
        $this->orderedAmount = $orderedAmount;
        $this->unitPrice     = $unitPrice;
        $this->note          = $note;
    }

    public function id(): PurchaseOrderLineId
    {
        return $this->id;
    }

    public function articleId(): ArticleId
    {
        return $this->articleId;
    }

    public function catalogEntryId(): SupplierCatalogEntryId
    {
        return $this->catalogEntryId;
    }

    public function orderedAmount(): string
    {
        return $this->orderedAmount;
    }

    public function orderedUnit(): UnitOfMeasure
    {
        return $this->orderedUnit;
    }

    public function unitPrice(): Money
    {
        return $this->unitPrice;
    }

    public function receivedAmount(): string
    {
        return $this->receivedAmount;
    }

    public function status(): PurchaseOrderLineStatus
    {
        return $this->status;
    }

    public function note(): ?string
    {
        return $this->note;
    }
}
