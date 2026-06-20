<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt;

use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierReceipt\Entity\SupplierReceiptLine;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptCompleted;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptCreated;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptLineAdded;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptLineRemoved;
use App\Context\Procurement\Domain\SupplierReceipt\Event\SupplierReceiptValidated;
use App\Context\Procurement\Domain\SupplierReceipt\Exception\ReceiptValidationException;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\DeliveryNoteReference;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\ReceiptMatchType;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptLineId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptStatus;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class SupplierReceipt extends AggregateRoot
{
    /** @var list<SupplierReceiptLine> */
    private array $lines = [];

    private function __construct(
        private SupplierReceiptId $id,
        private ClinicId $clinicId,
        private SupplierId $supplierId,
        private PurchaseOrderId $purchaseOrderId,
        private DeliveryNoteReference $deliveryNoteReference,
        private ReceiptMatchType $matchType,
        private SupplierReceiptStatus $status,
        private ?\DateTimeImmutable $validatedAt,
        private ?string $receivedBy,
        private ?string $comment,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        SupplierReceiptId $id,
        ClinicId $clinicId,
        SupplierId $supplierId,
        PurchaseOrderId $purchaseOrderId,
        DeliveryNoteReference $deliveryNoteReference,
        ReceiptMatchType $matchType,
        ?string $receivedBy = null,
        ?string $comment = null,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $now     = $createdAt ?? new \DateTimeImmutable();
        $receipt = new self(
            id: $id,
            clinicId: $clinicId,
            supplierId: $supplierId,
            purchaseOrderId: $purchaseOrderId,
            deliveryNoteReference: $deliveryNoteReference,
            matchType: $matchType,
            status: SupplierReceiptStatus::PENDING_REVIEW,
            validatedAt: null,
            receivedBy: $receivedBy,
            comment: $comment,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );
        $receipt->recordDomainEvent(new SupplierReceiptCreated(
            receiptId: $id->toString(),
            clinicId: $clinicId->toString(),
            supplierId: $supplierId->toString(),
            purchaseOrderId: $purchaseOrderId->toString(),
            deliveryNoteReference: $deliveryNoteReference->toString(),
            matchType: $matchType->value,
        ));

        return $receipt;
    }

    /**
     * @param list<SupplierReceiptLine> $lines
     */
    public static function reconstitute(
        SupplierReceiptId $id,
        ClinicId $clinicId,
        SupplierId $supplierId,
        PurchaseOrderId $purchaseOrderId,
        DeliveryNoteReference $deliveryNoteReference,
        ReceiptMatchType $matchType,
        SupplierReceiptStatus $status,
        ?\DateTimeImmutable $validatedAt,
        ?string $receivedBy,
        ?string $comment,
        array $lines,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $receipt = new self(
            id: $id,
            clinicId: $clinicId,
            supplierId: $supplierId,
            purchaseOrderId: $purchaseOrderId,
            deliveryNoteReference: $deliveryNoteReference,
            matchType: $matchType,
            status: $status,
            validatedAt: $validatedAt,
            receivedBy: $receivedBy,
            comment: $comment,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
        $receipt->lines = $lines;

        return $receipt;
    }

    public function addLine(SupplierReceiptLine $line, \DateTimeImmutable $updatedAt): void
    {
        if (SupplierReceiptStatus::PENDING_REVIEW !== $this->status) {
            throw new ReceiptValidationException('Cannot add line to a validated receipt.');
        }

        $this->lines[]   = $line;
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new SupplierReceiptLineAdded(
            receiptId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            lineId: $line->id()->toString(),
        ));
    }

    public function removeLine(SupplierReceiptLineId $lineId, \DateTimeImmutable $updatedAt): void
    {
        if (SupplierReceiptStatus::PENDING_REVIEW !== $this->status) {
            throw new ReceiptValidationException('Cannot remove line from a validated receipt.');
        }

        $this->lines = array_values(array_filter(
            $this->lines,
            static fn (SupplierReceiptLine $l) => !$l->id()->equals($lineId)
        ));
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new SupplierReceiptLineRemoved(
            receiptId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            lineId: $lineId->toString(),
        ));
    }

    public function updateComment(?string $comment, \DateTimeImmutable $updatedAt): void
    {
        if (SupplierReceiptStatus::PENDING_REVIEW !== $this->status) {
            throw new ReceiptValidationException('Cannot update comment on a validated receipt.');
        }

        $this->comment   = $comment;
        $this->updatedAt = $updatedAt;
    }

    public function validate(\DateTimeImmutable $validatedAt, \DateTimeImmutable $updatedAt): void
    {
        if (SupplierReceiptStatus::PENDING_REVIEW !== $this->status) {
            throw new ReceiptValidationException('Receipt is already validated.');
        }

        $this->status      = SupplierReceiptStatus::VALIDATED;
        $this->validatedAt = $validatedAt;
        $this->updatedAt   = $updatedAt;
        $this->recordDomainEvent(new SupplierReceiptValidated(
            receiptId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            validatedAt: $validatedAt->format(\DateTimeInterface::ATOM),
        ));
        $this->recordDomainEvent(new SupplierReceiptCompleted(
            receiptId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            supplierId: $this->supplierId->toString(),
            purchaseOrderId: $this->purchaseOrderId->toString(),
        ));
    }

    public function id(): SupplierReceiptId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function supplierId(): SupplierId
    {
        return $this->supplierId;
    }

    public function purchaseOrderId(): PurchaseOrderId
    {
        return $this->purchaseOrderId;
    }

    public function deliveryNoteReference(): DeliveryNoteReference
    {
        return $this->deliveryNoteReference;
    }

    public function matchType(): ReceiptMatchType
    {
        return $this->matchType;
    }

    public function status(): SupplierReceiptStatus
    {
        return $this->status;
    }

    public function validatedAt(): ?\DateTimeImmutable
    {
        return $this->validatedAt;
    }

    public function receivedBy(): ?string
    {
        return $this->receivedBy;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }

    /** @return list<SupplierReceiptLine> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
