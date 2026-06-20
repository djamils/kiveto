<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\Entity;

use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\LotInformation;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptLineId;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Money\Domain\ValueObject\Money;

final class SupplierReceiptLine
{
    private function __construct(
        private SupplierReceiptLineId $id,
        private PurchaseOrderLineId $purchaseOrderLineId,
        private ArticleId $articleId,
        private string $receivedAmount,
        private UnitOfMeasure $receivedUnit,
        private ?LotInformation $lotInformation,
        private ?Money $actualUnitPrice,
        private ?string $note,
    ) {
    }

    public static function create(
        SupplierReceiptLineId $id,
        PurchaseOrderLineId $purchaseOrderLineId,
        ArticleId $articleId,
        string $receivedAmount,
        UnitOfMeasure $receivedUnit,
        ?LotInformation $lotInformation = null,
        ?Money $actualUnitPrice = null,
        ?string $note = null,
    ): self {
        return new self($id, $purchaseOrderLineId, $articleId, $receivedAmount, $receivedUnit, $lotInformation, $actualUnitPrice, $note);
    }

    public static function reconstitute(
        SupplierReceiptLineId $id,
        PurchaseOrderLineId $purchaseOrderLineId,
        ArticleId $articleId,
        string $receivedAmount,
        UnitOfMeasure $receivedUnit,
        ?LotInformation $lotInformation,
        ?Money $actualUnitPrice,
        ?string $note,
    ): self {
        return new self($id, $purchaseOrderLineId, $articleId, $receivedAmount, $receivedUnit, $lotInformation, $actualUnitPrice, $note);
    }

    public function id(): SupplierReceiptLineId
    {
        return $this->id;
    }

    public function purchaseOrderLineId(): PurchaseOrderLineId
    {
        return $this->purchaseOrderLineId;
    }

    public function articleId(): ArticleId
    {
        return $this->articleId;
    }

    public function receivedAmount(): string
    {
        return $this->receivedAmount;
    }

    public function receivedUnit(): UnitOfMeasure
    {
        return $this->receivedUnit;
    }

    public function lotInformation(): ?LotInformation
    {
        return $this->lotInformation;
    }

    public function actualUnitPrice(): ?Money
    {
        return $this->actualUnitPrice;
    }

    public function note(): ?string
    {
        return $this->note;
    }
}
