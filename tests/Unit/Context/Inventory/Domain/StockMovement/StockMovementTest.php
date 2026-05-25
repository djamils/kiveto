<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Domain\StockMovement;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockMovement\Event\StockMovementRecorded;
use App\Context\Inventory\Domain\StockMovement\Exception\IncoherentMovementTypeReasonException;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use PHPUnit\Framework\TestCase;

final class StockMovementTest extends TestCase
{
    private const string MOVEMENT_ID = '01970001-0000-7000-8000-000000000020';
    private const string CLINIC_ID   = '550e8400-e29b-41d4-a716-446655440000';
    private const string ARTICLE_ID  = '550e8400-e29b-41d4-a716-446655440001';
    private const string LOT_ID      = '01970001-0000-7000-8000-000000000010';

    private UnitOfMeasure $unit;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->unit = UnitOfMeasure::fromString('UNIT');
        $this->now  = new \DateTimeImmutable('2026-01-15 10:00:00');
    }

    public function testRecordEmitsStockMovementRecordedEvent(): void
    {
        $movement = StockMovement::record(
            id: StockMovementId::fromString(self::MOVEMENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            lotId: LotId::fromString(self::LOT_ID),
            type: MovementType::IN,
            reason: MovementReason::RECEIPT_SUPPLIER,
            quantity: Quantity::of('10.000000', $this->unit),
            occurredAt: $this->now,
            reference: 'PO-2026-001',
            performedBy: 'user@example.com',
            note: 'Initial delivery',
            createdAt: $this->now,
        );

        $events = $movement->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockMovementRecorded::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(StockMovementRecorded::class, $event);
        self::assertSame(self::MOVEMENT_ID, $event->movementId);
        self::assertSame(self::CLINIC_ID, $event->clinicId);
        self::assertSame(self::ARTICLE_ID, $event->articleId);
        self::assertSame(self::LOT_ID, $event->lotId);
        self::assertSame('IN', $event->type);
        self::assertSame('RECEIPT_SUPPLIER', $event->reason);
        self::assertSame('10.000000', $event->quantityAmount);
        self::assertSame('UNIT', $event->quantityUnit);
        self::assertSame('PO-2026-001', $event->reference);
        self::assertSame('user@example.com', $event->performedBy);
        self::assertSame('Initial delivery', $event->note);
    }

    public function testRecordThrowsIncoherentMovementTypeReasonException(): void
    {
        $this->expectException(IncoherentMovementTypeReasonException::class);

        // type=IN + reason=CONSUMPTION_CONSULTATION is incoherent
        StockMovement::record(
            id: StockMovementId::fromString(self::MOVEMENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            lotId: LotId::fromString(self::LOT_ID),
            type: MovementType::IN,
            reason: MovementReason::CONSUMPTION_CONSULTATION,
            quantity: Quantity::of('5.000000', $this->unit),
            occurredAt: $this->now,
            reference: null,
            performedBy: null,
            note: null,
            createdAt: $this->now,
        );
    }

    public function testRecordThrowsOnZeroQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        StockMovement::record(
            id: StockMovementId::fromString(self::MOVEMENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            lotId: null,
            type: MovementType::IN,
            reason: MovementReason::RECEIPT_SUPPLIER,
            quantity: Quantity::zero($this->unit),
            occurredAt: $this->now,
            reference: null,
            performedBy: null,
            note: null,
            createdAt: $this->now,
        );
    }

    public function testAllInReasonsCombinationsAreValid(): void
    {
        $inReasons = [
            MovementReason::RECEIPT_SUPPLIER,
            MovementReason::RECEIPT_OPENING,
            MovementReason::RECEIPT_RETURN_FROM_CLIENT,
        ];

        foreach ($inReasons as $reason) {
            $movement = StockMovement::record(
                id: StockMovementId::fromString(self::MOVEMENT_ID),
                clinicId: ClinicId::fromString(self::CLINIC_ID),
                articleId: ArticleId::fromString(self::ARTICLE_ID),
                lotId: null,
                type: MovementType::IN,
                reason: $reason,
                quantity: Quantity::of('1.000000', $this->unit),
                occurredAt: $this->now,
                reference: null,
                performedBy: null,
                note: null,
                createdAt: $this->now,
            );

            self::assertSame(MovementType::IN, $movement->type());
            self::assertSame($reason, $movement->reason());
        }
    }

    public function testAllOutReasonsCombinationsAreValid(): void
    {
        $outReasons = [
            MovementReason::CONSUMPTION_CONSULTATION,
            MovementReason::SALE_RETAIL,
            MovementReason::LOSS_EXPIRY,
            MovementReason::LOSS_BREAKAGE,
            MovementReason::LOSS_THEFT,
            MovementReason::RECALL_RETURN_TO_SUPPLIER,
        ];

        foreach ($outReasons as $reason) {
            $movement = StockMovement::record(
                id: StockMovementId::fromString(self::MOVEMENT_ID),
                clinicId: ClinicId::fromString(self::CLINIC_ID),
                articleId: ArticleId::fromString(self::ARTICLE_ID),
                lotId: null,
                type: MovementType::OUT,
                reason: $reason,
                quantity: Quantity::of('1.000000', $this->unit),
                occurredAt: $this->now,
                reference: null,
                performedBy: null,
                note: null,
                createdAt: $this->now,
            );

            self::assertSame(MovementType::OUT, $movement->type());
            self::assertSame($reason, $movement->reason());
        }
    }

    public function testAllAdjustmentReasonsCombinationsAreValid(): void
    {
        $adjustmentReasons = [
            MovementReason::PHYSICAL_INVENTORY,
            MovementReason::CORRECTION,
        ];

        foreach ($adjustmentReasons as $reason) {
            $movement = StockMovement::record(
                id: StockMovementId::fromString(self::MOVEMENT_ID),
                clinicId: ClinicId::fromString(self::CLINIC_ID),
                articleId: ArticleId::fromString(self::ARTICLE_ID),
                lotId: null,
                type: MovementType::ADJUSTMENT,
                reason: $reason,
                quantity: Quantity::of('1.000000', $this->unit),
                occurredAt: $this->now,
                reference: null,
                performedBy: null,
                note: null,
                createdAt: $this->now,
            );

            self::assertSame(MovementType::ADJUSTMENT, $movement->type());
            self::assertSame($reason, $movement->reason());
        }
    }

    public function testRecordWithNullLotId(): void
    {
        // trackStock=false scenario: no lot assigned
        $movement = StockMovement::record(
            id: StockMovementId::fromString(self::MOVEMENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            lotId: null,
            type: MovementType::IN,
            reason: MovementReason::RECEIPT_SUPPLIER,
            quantity: Quantity::of('10.000000', $this->unit),
            occurredAt: $this->now,
            reference: null,
            performedBy: null,
            note: null,
            createdAt: $this->now,
        );

        $events = $movement->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(StockMovementRecorded::class, $events[0]);

        $event = $events[0];
        self::assertInstanceOf(StockMovementRecorded::class, $event);
        self::assertNull($event->lotId);
        self::assertNull($movement->lotId());
    }

    public function testReconstituteTrustsPersistedData(): void
    {
        $movement = StockMovement::reconstitute(
            id: StockMovementId::fromString(self::MOVEMENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            articleId: ArticleId::fromString(self::ARTICLE_ID),
            lotId: LotId::fromString(self::LOT_ID),
            type: MovementType::OUT,
            reason: MovementReason::CONSUMPTION_CONSULTATION,
            quantity: Quantity::of('3.000000', $this->unit),
            occurredAt: $this->now,
            reference: 'REF-999',
            performedBy: null,
            note: null,
            createdAt: $this->now,
        );

        // No events on reconstitution
        $events = $movement->pullDomainEvents();
        self::assertSame([], $events);

        // Data is correct
        self::assertSame(self::MOVEMENT_ID, $movement->id()->toString());
        self::assertSame(self::CLINIC_ID, $movement->clinicId()->toString());
        self::assertSame(self::ARTICLE_ID, $movement->articleId()->toString());
        self::assertSame(self::LOT_ID, $movement->lotId()?->toString());
        self::assertSame(MovementType::OUT, $movement->type());
        self::assertSame(MovementReason::CONSUMPTION_CONSULTATION, $movement->reason());
        self::assertSame('3.000000', $movement->quantity()->amount());
        self::assertSame('REF-999', $movement->reference());
    }
}
