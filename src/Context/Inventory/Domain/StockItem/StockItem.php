<?php

declare(strict_types=1);

namespace App\Context\Inventory\Domain\StockItem;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\Shared\ValueObject\SupplierId;
use App\Context\Inventory\Domain\StockItem\Entity\Lot;
use App\Context\Inventory\Domain\StockItem\Event\LotAdded;
use App\Context\Inventory\Domain\StockItem\Event\LotDepleted;
use App\Context\Inventory\Domain\StockItem\Event\LotExpired;
use App\Context\Inventory\Domain\StockItem\Event\LotRecalled;
use App\Context\Inventory\Domain\StockItem\Event\StockAdjusted;
use App\Context\Inventory\Domain\StockItem\Event\StockConsumed;
use App\Context\Inventory\Domain\StockItem\Event\StockItemArchived;
use App\Context\Inventory\Domain\StockItem\Event\StockItemOpened;
use App\Context\Inventory\Domain\StockItem\Event\StockItemRestored;
use App\Context\Inventory\Domain\StockItem\Event\StockReceived;
use App\Context\Inventory\Domain\StockItem\Event\StockReservationReleased;
use App\Context\Inventory\Domain\StockItem\Event\StockReserved;
use App\Context\Inventory\Domain\StockItem\Event\StockThresholdChanged;
use App\Context\Inventory\Domain\StockItem\Exception\ArchivedStockItemException;
use App\Context\Inventory\Domain\StockItem\Exception\InconsistentLotDataException;
use App\Context\Inventory\Domain\StockItem\Exception\InsufficientStockException;
use App\Context\Inventory\Domain\StockItem\Exception\LotAlreadyTerminatedException;
use App\Context\Inventory\Domain\StockItem\Exception\LotNotFoundException;
use App\Context\Inventory\Domain\StockItem\Exception\NegativeQuantityException;
use App\Context\Inventory\Domain\StockItem\Exception\StockNotEmptyOnArchiveException;
use App\Context\Inventory\Domain\StockItem\Exception\UnitMismatchException;
use App\Context\Inventory\Domain\StockItem\Service\FefoSelector;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotConsumption;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockThreshold;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;

final class StockItem extends AggregateRoot
{
    /**
     * @param list<Lot> $lots
     */
    private function __construct(
        private readonly StockItemId $id,
        private readonly ClinicId $clinicId,
        private readonly ArticleId $articleId,
        private readonly UnitOfMeasure $unit,
        private Quantity $totalOnHand,
        private Quantity $reservedQuantity,
        /** @var list<Lot> */
        private array $lots,
        private StockThreshold $minimumThreshold,
        private readonly bool $trackStock,
        private StockItemStatus $status,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function open(
        StockItemId $id,
        ClinicId $clinicId,
        ArticleId $articleId,
        UnitOfMeasure $unit,
        StockThreshold $threshold,
        bool $trackStock,
        ClockInterface $clock,
    ): self {
        $now = $clock->now();

        $stockItem = new self(
            id: $id,
            clinicId: $clinicId,
            articleId: $articleId,
            unit: $unit,
            totalOnHand: Quantity::zero($unit),
            reservedQuantity: Quantity::zero($unit),
            lots: [],
            minimumThreshold: $threshold,
            trackStock: $trackStock,
            status: StockItemStatus::ACTIVE,
            createdAt: $now,
            updatedAt: $now,
        );

        $stockItem->recordDomainEvent(new StockItemOpened(
            stockItemId: $id->toString(),
            clinicId: $clinicId->toString(),
            articleId: $articleId->toString(),
            unit: $unit->toString(),
            thresholdAmount: $threshold->quantity()->amount(),
            thresholdUnit: $threshold->quantity()->unit()->toString(),
            thresholdType: $threshold->type()->value,
            trackStock: $trackStock,
        ));

        return $stockItem;
    }

    /**
     * Reconstitutes a StockItem from persisted data. No events recorded.
     * Trusts persisted totalOnHand — does NOT recompute from lots sum.
     *
     * @param list<Lot> $lots
     */
    public static function reconstitute(
        StockItemId $id,
        ClinicId $clinicId,
        ArticleId $articleId,
        UnitOfMeasure $unit,
        Quantity $totalOnHand,
        Quantity $reservedQuantity,
        array $lots,
        StockThreshold $minimumThreshold,
        bool $trackStock,
        StockItemStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            clinicId: $clinicId,
            articleId: $articleId,
            unit: $unit,
            totalOnHand: $totalOnHand,
            reservedQuantity: $reservedQuantity,
            lots: $lots,
            minimumThreshold: $minimumThreshold,
            trackStock: $trackStock,
            status: $status,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    /**
     * Receives stock into a lot.
     * For trackStock=false: records StockReceived but creates no lot, totalOnHand unchanged.
     *
     * @param LotId $newLotId pre-generated ID for the new lot (used only when a new lot is created)
     *
     * @return ?LotId the ID of the lot that was created or incremented, or null when trackStock=false
     */
    public function receiveStock(
        LotNumber $lotNumber,
        Quantity $quantity,
        \DateTimeImmutable $expiryDate,
        ?SupplierId $sourceSupplier,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $updatedAt,
        LotId $newLotId,
    ): ?LotId {
        $this->assertNotArchived();

        if (!$this->unit->equals($quantity->unit())) {
            throw new UnitMismatchException($this->unit->toString(), $quantity->unit()->toString());
        }

        if (!$this->trackStock) {
            $this->recordDomainEvent(new StockReceived(
                stockItemId: $this->id->toString(),
                clinicId: $this->clinicId->toString(),
                articleId: $this->articleId->toString(),
                lotId: null,
                lotNumber: $lotNumber->toString(),
                quantityAmount: $quantity->amount(),
                quantityUnit: $quantity->unit()->toString(),
                expiryDate: $expiryDate->format('Y-m-d'),
                sourceSupplierId: $sourceSupplier?->toString(),
                occurredAt: $occurredAt->format(\DateTimeInterface::ATOM),
            ));

            return null;
        }

        $existingLot = $this->findLotByNumber($lotNumber);
        $usedLotId   = null;

        if (null !== $existingLot) {
            if (LotStatus::ACTIVE === $existingLot->status()) {
                if ($existingLot->expiryDate()->format('Y-m-d') !== $expiryDate->format('Y-m-d')) {
                    throw new InconsistentLotDataException($lotNumber->toString());
                }
                $existingLot->incrementQuantity($quantity, $updatedAt);
                $usedLotId = $existingLot->id();
            } else {
                throw new LotAlreadyTerminatedException($lotNumber->toString(), $existingLot->status()->value);
            }
        } else {
            $lot = new Lot(
                id: $newLotId,
                number: $lotNumber,
                quantity: $quantity,
                expiryDate: $expiryDate,
                receivedAt: $occurredAt,
                sourceSupplier: $sourceSupplier,
                status: LotStatus::ACTIVE,
                createdAt: $occurredAt,
                updatedAt: $updatedAt,
            );
            $this->lots[] = $lot;
            $usedLotId    = $newLotId;

            $this->recordDomainEvent(new LotAdded(
                stockItemId: $this->id->toString(),
                lotId: $newLotId->toString(),
                lotNumber: $lotNumber->toString(),
                quantityAmount: $quantity->amount(),
                quantityUnit: $quantity->unit()->toString(),
                expiryDate: $expiryDate->format('Y-m-d'),
                sourceSupplierId: $sourceSupplier?->toString(),
            ));
        }

        $this->totalOnHand = $this->totalOnHand->add($quantity);
        $this->updatedAt   = $updatedAt;

        $this->assertLotsSumConsistency();

        $this->recordDomainEvent(new StockReceived(
            stockItemId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            articleId: $this->articleId->toString(),
            lotId: $usedLotId->toString(),
            lotNumber: $lotNumber->toString(),
            quantityAmount: $quantity->amount(),
            quantityUnit: $quantity->unit()->toString(),
            expiryDate: $expiryDate->format('Y-m-d'),
            sourceSupplierId: $sourceSupplier?->toString(),
            occurredAt: $occurredAt->format(\DateTimeInterface::ATOM),
        ));

        return $usedLotId;
    }

    /**
     * @return list<LotConsumption>
     */
    public function consumeStock(
        Quantity $quantity,
        FefoSelector $fefoSelector,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $updatedAt,
    ): array {
        $this->assertNotArchived();

        if (!$this->trackStock) {
            return [];
        }

        $available = $this->availableQuantity();
        if ($available->isLessThan($quantity)) {
            throw new InsufficientStockException(
                $available->amount(),
                $quantity->amount(),
                $this->unit->toString()
            );
        }

        $orderedLots = $fefoSelector->selectOrderedForConsumption($this->lots);

        if ([] === $orderedLots) {
            throw new InsufficientStockException(
                '0.000000',
                $quantity->amount(),
                $this->unit->toString()
            );
        }

        $remaining    = $quantity;
        $consumptions = [];

        foreach ($orderedLots as $lot) {
            if ($remaining->isZero()) {
                break;
            }

            if ($lot->quantity()->isGreaterOrEqual($remaining)) {
                $consumed  = $remaining;
                $remaining = Quantity::zero($this->unit);

                $lot->decrementQuantity($consumed, $updatedAt);

                if ($lot->quantity()->isZero()) {
                    $lot->markAs(LotStatus::DEPLETED, $updatedAt);
                    $this->recordDomainEvent(new LotDepleted(
                        stockItemId: $this->id->toString(),
                        lotId: $lot->id()->toString(),
                        lotNumber: $lot->number()->toString(),
                    ));
                }

                $this->recordDomainEvent(new StockConsumed(
                    stockItemId: $this->id->toString(),
                    clinicId: $this->clinicId->toString(),
                    articleId: $this->articleId->toString(),
                    lotId: $lot->id()->toString(),
                    quantityAmount: $consumed->amount(),
                    quantityUnit: $consumed->unit()->toString(),
                    occurredAt: $occurredAt->format(\DateTimeInterface::ATOM),
                ));

                $consumptions[] = new LotConsumption($lot->id(), $consumed);
            } else {
                // Full lot consumed
                $consumed  = $lot->quantity();
                $remaining = $remaining->subtract($consumed);

                $lot->decrementQuantity($consumed, $updatedAt);
                $lot->markAs(LotStatus::DEPLETED, $updatedAt);

                // Event ordering: LotDepleted THEN StockConsumed (state-change first)
                $this->recordDomainEvent(new LotDepleted(
                    stockItemId: $this->id->toString(),
                    lotId: $lot->id()->toString(),
                    lotNumber: $lot->number()->toString(),
                ));

                $this->recordDomainEvent(new StockConsumed(
                    stockItemId: $this->id->toString(),
                    clinicId: $this->clinicId->toString(),
                    articleId: $this->articleId->toString(),
                    lotId: $lot->id()->toString(),
                    quantityAmount: $consumed->amount(),
                    quantityUnit: $consumed->unit()->toString(),
                    occurredAt: $occurredAt->format(\DateTimeInterface::ATOM),
                ));

                $consumptions[] = new LotConsumption($lot->id(), $consumed);
            }
        }

        $this->totalOnHand = $this->totalOnHand->subtract($quantity);
        $this->updatedAt   = $updatedAt;

        $this->assertLotsSumConsistency();

        return $consumptions;
    }

    public function reserveStock(Quantity $quantity, \DateTimeImmutable $updatedAt): void
    {
        $this->assertNotArchived();

        if (!$this->trackStock) {
            return;
        }

        $available = $this->availableQuantity();
        if ($available->isLessThan($quantity)) {
            throw new InsufficientStockException(
                $available->amount(),
                $quantity->amount(),
                $this->unit->toString()
            );
        }

        $this->reservedQuantity = $this->reservedQuantity->add($quantity);
        $this->updatedAt        = $updatedAt;

        $this->recordDomainEvent(new StockReserved(
            stockItemId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            articleId: $this->articleId->toString(),
            quantityAmount: $quantity->amount(),
            quantityUnit: $quantity->unit()->toString(),
        ));
    }

    public function releaseReservation(Quantity $quantity, \DateTimeImmutable $updatedAt): void
    {
        $this->assertNotArchived();

        if (!$this->trackStock) {
            return;
        }

        if ($this->reservedQuantity->isLessThan($quantity)) {
            throw new NegativeQuantityException();
        }

        $this->reservedQuantity = $this->reservedQuantity->subtract($quantity);
        $this->updatedAt        = $updatedAt;

        $this->recordDomainEvent(new StockReservationReleased(
            stockItemId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            articleId: $this->articleId->toString(),
            quantityAmount: $quantity->amount(),
            quantityUnit: $quantity->unit()->toString(),
        ));
    }

    public function adjustStock(
        LotId $lotId,
        Quantity $newLotQuantity,
        string $reason,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $updatedAt,
    ): void {
        $this->assertNotArchived();

        if (!$this->trackStock) {
            return;
        }

        $lot = $this->findLotById($lotId);

        if (null === $lot) {
            throw new LotNotFoundException($lotId->toString());
        }

        /** @var numeric-string $deltaAmount */
        $deltaAmount = bcsub($newLotQuantity->amount(), $lot->quantity()->amount(), 6);

        $lot->setQuantity($newLotQuantity, $updatedAt);

        /** @var numeric-string $zero */
        $zero     = '0';
        $deltaCmp = bccomp($deltaAmount, $zero, 6);

        if ($deltaCmp > 0) {
            $this->totalOnHand = $this->totalOnHand->add(Quantity::of($deltaAmount, $this->unit));
        } elseif ($deltaCmp < 0) {
            $absAmount         = ltrim($deltaAmount, '-');
            $this->totalOnHand = $this->totalOnHand->subtract(Quantity::of($absAmount, $this->unit));
        }

        if ($newLotQuantity->isZero()) {
            $lot->markAs(LotStatus::DEPLETED, $updatedAt);
            $this->recordDomainEvent(new LotDepleted(
                stockItemId: $this->id->toString(),
                lotId: $lot->id()->toString(),
                lotNumber: $lot->number()->toString(),
            ));
        }

        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new StockAdjusted(
            stockItemId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            articleId: $this->articleId->toString(),
            lotId: $lotId->toString(),
            deltaAmount: $deltaAmount,
            deltaUnit: $this->unit->toString(),
            newLotQuantityAmount: $newLotQuantity->amount(),
            reason: $reason,
            occurredAt: $occurredAt->format(\DateTimeInterface::ATOM),
        ));

        $this->assertLotsSumConsistency();
    }

    public function changeThreshold(StockThreshold $threshold, \DateTimeImmutable $updatedAt): void
    {
        $this->assertNotArchived();

        $this->minimumThreshold = $threshold;
        $this->updatedAt        = $updatedAt;

        $this->recordDomainEvent(new StockThresholdChanged(
            stockItemId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            articleId: $this->articleId->toString(),
            thresholdAmount: $threshold->quantity()->amount(),
            thresholdUnit: $threshold->quantity()->unit()->toString(),
            thresholdType: $threshold->type()->value,
        ));
    }

    /**
     * Marks a lot as recalled. Returns the recalled quantity for use by the handler.
     */
    public function markLotAsRecalled(
        LotId $lotId,
        string $reason,
        \DateTimeImmutable $occurredAt,
        \DateTimeImmutable $updatedAt,
    ): Quantity {
        $this->assertNotArchived();

        $lot = $this->findLotById($lotId);

        if (null === $lot) {
            throw new LotNotFoundException($lotId->toString());
        }

        $recalledQuantity = $lot->quantity();

        $lot->setQuantityZero($updatedAt);
        $lot->markAs(LotStatus::RECALLED, $updatedAt);

        $this->totalOnHand = $this->totalOnHand->subtract($recalledQuantity);
        $this->updatedAt   = $updatedAt;

        $this->recordDomainEvent(new LotRecalled(
            stockItemId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            lotId: $lotId->toString(),
            lotNumber: $lot->number()->toString(),
            recalledQuantityAmount: $recalledQuantity->amount(),
            recalledQuantityUnit: $recalledQuantity->unit()->toString(),
            reason: $reason,
            occurredAt: $occurredAt->format(\DateTimeInterface::ATOM),
        ));

        $this->assertLotsSumConsistency();

        return $recalledQuantity;
    }

    /**
     * Expires all ACTIVE lots whose expiryDate < today.
     * EXPIRED lots keep their quantity (pharmacovigilance audit — not zeroed).
     */
    public function expireOutdatedLots(\DateTimeImmutable $today, \DateTimeImmutable $updatedAt): void
    {
        $this->assertNotArchived();

        foreach ($this->lots as $lot) {
            if (LotStatus::ACTIVE !== $lot->status()) {
                continue;
            }

            if ($lot->expiryDate() >= $today) {
                continue;
            }

            $quantityAtExpiry = $lot->quantity();

            $lot->markAs(LotStatus::EXPIRED, $updatedAt);

            $this->totalOnHand = $this->totalOnHand->subtract($quantityAtExpiry);

            $this->recordDomainEvent(new LotExpired(
                stockItemId: $this->id->toString(),
                clinicId: $this->clinicId->toString(),
                lotId: $lot->id()->toString(),
                lotNumber: $lot->number()->toString(),
                quantityAtExpiry: $quantityAtExpiry->amount(),
                quantityUnit: $quantityAtExpiry->unit()->toString(),
                expiryDate: $lot->expiryDate()->format('Y-m-d'),
            ));
        }

        $this->updatedAt = $updatedAt;
    }

    public function archive(\DateTimeImmutable $updatedAt): void
    {
        $this->assertNotArchived();

        if (!$this->totalOnHand->isZero()) {
            throw new StockNotEmptyOnArchiveException($this->id->toString(), $this->totalOnHand->amount());
        }

        $this->status    = StockItemStatus::ARCHIVED;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new StockItemArchived(
            stockItemId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            articleId: $this->articleId->toString(),
        ));
    }

    public function restore(\DateTimeImmutable $updatedAt): void
    {
        if (StockItemStatus::ACTIVE === $this->status) {
            return;
        }

        $this->status    = StockItemStatus::ACTIVE;
        $this->updatedAt = $updatedAt;

        $this->recordDomainEvent(new StockItemRestored(
            stockItemId: $this->id->toString(),
            clinicId: $this->clinicId->toString(),
            articleId: $this->articleId->toString(),
        ));
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function id(): StockItemId
    {
        return $this->id;
    }

    public function clinicId(): ClinicId
    {
        return $this->clinicId;
    }

    public function articleId(): ArticleId
    {
        return $this->articleId;
    }

    public function unit(): UnitOfMeasure
    {
        return $this->unit;
    }

    public function totalOnHand(): Quantity
    {
        return $this->totalOnHand;
    }

    public function reservedQuantity(): Quantity
    {
        return $this->reservedQuantity;
    }

    public function availableQuantity(): Quantity
    {
        return $this->totalOnHand->subtract($this->reservedQuantity);
    }

    /** @return list<Lot> */
    public function lots(): array
    {
        return $this->lots;
    }

    public function minimumThreshold(): StockThreshold
    {
        return $this->minimumThreshold;
    }

    public function trackStock(): bool
    {
        return $this->trackStock;
    }

    public function status(): StockItemStatus
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function assertNotArchived(): void
    {
        if (StockItemStatus::ARCHIVED === $this->status) {
            throw new ArchivedStockItemException($this->id->toString());
        }
    }

    private function findLotByNumber(LotNumber $lotNumber): ?Lot
    {
        foreach ($this->lots as $lot) {
            if ($lot->number()->equals($lotNumber)) {
                return $lot;
            }
        }

        return null;
    }

    private function findLotById(LotId $lotId): ?Lot
    {
        foreach ($this->lots as $lot) {
            if ($lot->id()->equals($lotId)) {
                return $lot;
            }
        }

        return null;
    }

    /**
     * Asserts sum(ACTIVE lots quantities) == totalOnHand.
     * EXPIRED lots keep their quantity for audit but are excluded from totalOnHand.
     * RECALLED and DEPLETED lots have quantity=0 (no contribution either way).
     */
    private function assertLotsSumConsistency(): void
    {
        if (!$this->trackStock) {
            return;
        }

        /** @var numeric-string $sum */
        $sum = '0.000000';

        foreach ($this->lots as $lot) {
            if (LotStatus::ACTIVE === $lot->status()) {
                /** @var numeric-string $sum */
                $sum = bcadd($sum, $lot->quantity()->amount(), 6);
            }
        }

        if (0 !== bccomp($sum, $this->totalOnHand->amount(), 6)) {
            throw new \LogicException(\sprintf(
                'Invariant violated on StockItem "%s": sum of ACTIVE lot quantities (%s) != totalOnHand (%s).',
                $this->id->toString(),
                $sum,
                $this->totalOnHand->amount()
            ));
        }
    }
}
