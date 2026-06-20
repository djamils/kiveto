<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\PurchaseOrder;

use App\Context\Procurement\Domain\PurchaseOrder\Entity\PurchaseOrderLine;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderCancelled;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderClosed;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderConfirmed;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderCreated;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderFullyReceived;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineAdded;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineCancelled;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineRemoved;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderLineUpdated;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderPartiallyReceived;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderSendFailed;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderSubmitted;
use App\Context\Procurement\Domain\PurchaseOrder\Event\PurchaseOrderSubmittingStarted;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\EmptyPurchaseOrderException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\InvalidPurchaseOrderStatusTransitionException;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineStatus;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderStatus;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;

final class PurchaseOrder extends AggregateRoot
{
    /** @var list<PurchaseOrderLine> */
    private array $lines = [];

    private function __construct(
        private PurchaseOrderId $id,
        private ClinicId $clinicId,
        private SupplierId $supplierId,
        private SupplierAccountId $supplierAccountId,
        private PurchaseOrderNumber $orderNumber,
        private CurrencyCode $currency,
        private PurchaseOrderStatus $status,
        private ?ExternalReference $externalReference,
        private Address $deliveryAddress,
        private ?string $pdfFileId,
        private ?\DateTimeImmutable $submittedAt,
        private ?\DateTimeImmutable $confirmedAt,
        private int $version,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        PurchaseOrderId $id,
        ClinicId $clinicId,
        SupplierId $supplierId,
        SupplierAccountId $supplierAccountId,
        PurchaseOrderNumber $orderNumber,
        CurrencyCode $currency,
        Address $deliveryAddress,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        $now = $createdAt ?? new \DateTimeImmutable();
        $po  = new self(
            id: $id,
            clinicId: $clinicId,
            supplierId: $supplierId,
            supplierAccountId: $supplierAccountId,
            orderNumber: $orderNumber,
            currency: $currency,
            status: PurchaseOrderStatus::DRAFT,
            externalReference: null,
            deliveryAddress: $deliveryAddress,
            pdfFileId: null,
            submittedAt: null,
            confirmedAt: null,
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );
        $po->recordDomainEvent(new PurchaseOrderCreated(
            purchaseOrderId: $id->toString(),
            clinicId: $clinicId->toString(),
            supplierId: $supplierId->toString(),
            orderNumber: $orderNumber->toString(),
            currency: $currency->toString(),
        ));

        return $po;
    }

    /**
     * @param list<PurchaseOrderLine> $lines
     */
    public static function reconstitute(
        PurchaseOrderId $id,
        ClinicId $clinicId,
        SupplierId $supplierId,
        SupplierAccountId $supplierAccountId,
        PurchaseOrderNumber $orderNumber,
        CurrencyCode $currency,
        PurchaseOrderStatus $status,
        ?ExternalReference $externalReference,
        Address $deliveryAddress,
        ?string $pdfFileId,
        ?\DateTimeImmutable $submittedAt,
        ?\DateTimeImmutable $confirmedAt,
        array $lines,
        int $version,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $po = new self(
            id: $id,
            clinicId: $clinicId,
            supplierId: $supplierId,
            supplierAccountId: $supplierAccountId,
            orderNumber: $orderNumber,
            currency: $currency,
            status: $status,
            externalReference: $externalReference,
            deliveryAddress: $deliveryAddress,
            pdfFileId: $pdfFileId,
            submittedAt: $submittedAt,
            confirmedAt: $confirmedAt,
            version: $version,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
        $po->lines = $lines;

        return $po;
    }

    /**
     * @phpstan-param numeric-string $orderedAmount
     */
    public function addLine(
        PurchaseOrderLineId $lineId,
        ArticleId $articleId,
        SupplierCatalogEntryId $catalogEntryId,
        string $orderedAmount,
        UnitOfMeasure $orderedUnit,
        Money $unitPrice,
        ?string $note,
        \DateTimeImmutable $updatedAt,
    ): void {
        if (PurchaseOrderStatus::DRAFT !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot add line to PurchaseOrder in status %s.', $this->status->value)
            );
        }

        $line            = PurchaseOrderLine::create($lineId, $articleId, $catalogEntryId, $orderedAmount, $orderedUnit, $unitPrice, $note);
        $this->lines[]   = $line;
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderLineAdded(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            lineId: $lineId->toString(),
        ));
    }

    /**
     * @phpstan-param numeric-string $orderedAmount
     */
    public function updateLine(
        PurchaseOrderLineId $lineId,
        string $orderedAmount,
        Money $unitPrice,
        ?string $note,
        \DateTimeImmutable $updatedAt,
    ): void {
        if (PurchaseOrderStatus::DRAFT !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot update line on PurchaseOrder in status %s.', $this->status->value)
            );
        }

        $line = $this->findLine($lineId);
        $line->update($orderedAmount, $unitPrice, $note);
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderLineUpdated(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            lineId: $lineId->toString(),
        ));
    }

    public function removeLine(PurchaseOrderLineId $lineId, \DateTimeImmutable $updatedAt): void
    {
        if (PurchaseOrderStatus::DRAFT !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot remove line from PurchaseOrder in status %s.', $this->status->value)
            );
        }

        $this->lines = array_values(array_filter(
            $this->lines,
            static fn (PurchaseOrderLine $l) => !$l->id()->equals($lineId)
        ));
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderLineRemoved(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            lineId: $lineId->toString(),
        ));
    }

    public function markAsSubmitting(\DateTimeImmutable $updatedAt): void
    {
        if (PurchaseOrderStatus::DRAFT !== $this->status && PurchaseOrderStatus::SEND_FAILED !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot submit PurchaseOrder in status %s. Expected DRAFT or SEND_FAILED.', $this->status->value)
            );
        }

        $activeLines = array_values(array_filter(
            $this->lines,
            static fn (PurchaseOrderLine $l) => PurchaseOrderLineStatus::ACTIVE === $l->status()
        ));

        if ([] === $activeLines) {
            throw new EmptyPurchaseOrderException($this->id->toString());
        }

        $this->status    = PurchaseOrderStatus::SUBMITTING;
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderSubmittingStarted(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function submit(ExternalReference $ref, \DateTimeImmutable $submittedAt, \DateTimeImmutable $updatedAt): void
    {
        if (PurchaseOrderStatus::SUBMITTING !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot submit PurchaseOrder in status %s. Expected SUBMITTING.', $this->status->value)
            );
        }

        $this->status            = PurchaseOrderStatus::SUBMITTED;
        $this->externalReference = $ref;
        $this->submittedAt       = $submittedAt;
        $this->updatedAt         = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderSubmitted(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            externalReference: $ref->value,
            externalProvidedBy: $ref->providedBy,
            submittedAt: $submittedAt->format(\DateTimeInterface::ATOM),
        ));
    }

    public function markAsSendingFailed(string $reason, \DateTimeImmutable $updatedAt): void
    {
        if (PurchaseOrderStatus::SUBMITTING !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot mark send failed on PurchaseOrder in status %s.', $this->status->value)
            );
        }

        $this->status    = PurchaseOrderStatus::SEND_FAILED;
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderSendFailed(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            reason: $reason,
        ));
    }

    public function retryReset(\DateTimeImmutable $updatedAt): void
    {
        if (PurchaseOrderStatus::SEND_FAILED !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot retry reset PurchaseOrder in status %s. Expected SEND_FAILED.', $this->status->value)
            );
        }

        $this->status    = PurchaseOrderStatus::DRAFT;
        $this->updatedAt = $updatedAt;
    }

    public function confirm(\DateTimeImmutable $confirmedAt, \DateTimeImmutable $updatedAt): void
    {
        if (PurchaseOrderStatus::SUBMITTED !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot confirm PurchaseOrder in status %s. Expected SUBMITTED.', $this->status->value)
            );
        }

        $this->status      = PurchaseOrderStatus::CONFIRMED;
        $this->confirmedAt = $confirmedAt;
        $this->updatedAt   = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderConfirmed(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            confirmedAt: $confirmedAt->format(\DateTimeInterface::ATOM),
        ));
    }

    /**
     * Records partial reception (some lines still have remaining quantity).
     *
     * @param array<string, numeric-string> $receivedByLineId map of lineId => receivedAmount
     */
    public function recordPartialReception(array $receivedByLineId, \DateTimeImmutable $updatedAt): void
    {
        if (PurchaseOrderStatus::CONFIRMED !== $this->status && PurchaseOrderStatus::PARTIALLY_RECEIVED !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot record partial reception on PurchaseOrder in status %s.', $this->status->value)
            );
        }

        foreach ($receivedByLineId as $lineIdStr => $amount) {
            $lineId = PurchaseOrderLineId::fromString($lineIdStr);
            $line   = $this->findLine($lineId);
            $line->recordReception($amount);
        }

        $this->status    = PurchaseOrderStatus::PARTIALLY_RECEIVED;
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderPartiallyReceived(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    /**
     * Records full reception (all lines fully received).
     *
     * @param array<string, numeric-string> $receivedByLineId map of lineId => receivedAmount
     */
    public function recordFullReception(array $receivedByLineId, \DateTimeImmutable $updatedAt): void
    {
        if (PurchaseOrderStatus::CONFIRMED !== $this->status && PurchaseOrderStatus::PARTIALLY_RECEIVED !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot record full reception on PurchaseOrder in status %s.', $this->status->value)
            );
        }

        foreach ($receivedByLineId as $lineIdStr => $amount) {
            $lineId = PurchaseOrderLineId::fromString($lineIdStr);
            $line   = $this->findLine($lineId);
            $line->recordReception($amount);
        }

        $this->status    = PurchaseOrderStatus::RECEIVED;
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderFullyReceived(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function cancelLine(PurchaseOrderLineId $lineId, string $reason, \DateTimeImmutable $updatedAt): void
    {
        $allowedStatuses = [
            PurchaseOrderStatus::SUBMITTED,
            PurchaseOrderStatus::CONFIRMED,
            PurchaseOrderStatus::PARTIALLY_RECEIVED,
        ];

        if (!\in_array($this->status, $allowedStatuses, true)) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot cancel line on PurchaseOrder in status %s.', $this->status->value)
            );
        }

        $line = $this->findLine($lineId);
        $line->cancel($reason);
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderLineCancelled(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            lineId: $lineId->toString(),
            reason: $reason,
        ));
    }

    public function close(\DateTimeImmutable $updatedAt): void
    {
        if (PurchaseOrderStatus::RECEIVED !== $this->status && PurchaseOrderStatus::PARTIALLY_RECEIVED !== $this->status) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot close PurchaseOrder in status %s.', $this->status->value)
            );
        }

        // Auto-cancel remaining ACTIVE lines on partial close
        if (PurchaseOrderStatus::PARTIALLY_RECEIVED === $this->status) {
            foreach ($this->lines as $line) {
                if (PurchaseOrderLineStatus::ACTIVE === $line->status()) {
                    $line->cancel('Auto-cancelled on PO close');
                }
            }
        }

        $this->status    = PurchaseOrderStatus::CLOSED;
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderClosed(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
        ));
    }

    public function cancel(string $reason, \DateTimeImmutable $updatedAt): void
    {
        $allowedStatuses = [
            PurchaseOrderStatus::DRAFT,
            PurchaseOrderStatus::SUBMITTED,
            PurchaseOrderStatus::CONFIRMED,
            PurchaseOrderStatus::SEND_FAILED,
        ];

        if (!\in_array($this->status, $allowedStatuses, true)) {
            throw new InvalidPurchaseOrderStatusTransitionException(
                \sprintf('Cannot cancel PurchaseOrder in status %s.', $this->status->value)
            );
        }

        $this->status    = PurchaseOrderStatus::CANCELLED;
        $this->updatedAt = $updatedAt;
        $this->recordDomainEvent(new PurchaseOrderCancelled(
            purchaseOrderId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            reason: $reason,
        ));
    }

    public function totalAmount(): Money
    {
        $total = 0;
        foreach ($this->lines as $line) {
            if (PurchaseOrderLineStatus::CANCELLED === $line->status()) {
                continue;
            }

            $total += $line->lineTotal()->minorUnits();
        }

        return Money::fromMinorUnits($total, $this->currency);
    }

    /** @return list<PurchaseOrderLine> */
    public function linesActiveRemaining(): array
    {
        return array_values(array_filter(
            $this->lines,
            static fn (PurchaseOrderLine $l) => PurchaseOrderLineStatus::ACTIVE === $l->status()
        ));
    }

    public function daysSinceSubmission(): int
    {
        if (null === $this->submittedAt) {
            return 0;
        }

        $diff = (new \DateTimeImmutable())->diff($this->submittedAt);

        return (int) $diff->days;
    }

    public function id(): PurchaseOrderId
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

    public function supplierAccountId(): SupplierAccountId
    {
        return $this->supplierAccountId;
    }

    public function orderNumber(): PurchaseOrderNumber
    {
        return $this->orderNumber;
    }

    public function currency(): CurrencyCode
    {
        return $this->currency;
    }

    public function status(): PurchaseOrderStatus
    {
        return $this->status;
    }

    public function externalReference(): ?ExternalReference
    {
        return $this->externalReference;
    }

    public function deliveryAddress(): Address
    {
        return $this->deliveryAddress;
    }

    public function pdfFileId(): ?string
    {
        return $this->pdfFileId;
    }

    public function submittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function confirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    /** @return list<PurchaseOrderLine> */
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

    private function findLine(PurchaseOrderLineId $lineId): PurchaseOrderLine
    {
        foreach ($this->lines as $line) {
            if ($line->id()->equals($lineId)) {
                return $line;
            }
        }

        throw new \RuntimeException(\sprintf('PurchaseOrderLine "%s" not found.', $lineId->toString()));
    }
}
