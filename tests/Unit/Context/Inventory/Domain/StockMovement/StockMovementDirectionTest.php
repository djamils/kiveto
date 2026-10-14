<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Domain\StockMovement;

use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Context\Inventory\Domain\StockMovement\StockMovement;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementReason;
use App\Context\Inventory\Domain\StockMovement\ValueObject\MovementType;
use App\Context\Inventory\Domain\StockMovement\ValueObject\StockMovementId;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The quantity of a movement is always positive: an adjustment says which
 * way it went, and the stock page's own withdrawals (internal use, a return
 * that is not a recall) leave the shelf like any other.
 */
#[CoversClass(StockMovement::class)]
final class StockMovementDirectionTest extends TestCase
{
    public function testAnAdjustmentRemembersThatItTookStockAway(): void
    {
        $movement = $this->movement(MovementType::ADJUSTMENT, MovementReason::PHYSICAL_INVENTORY, decrease: true);

        self::assertTrue($movement->isDecrease());

        $copy = StockMovement::reconstitute(
            id: $movement->id(),
            clinicId: $movement->clinicId(),
            articleId: $movement->articleId(),
            lotId: null,
            type: $movement->type(),
            reason: $movement->reason(),
            quantity: $movement->quantity(),
            occurredAt: $movement->occurredAt(),
            reference: null,
            performedBy: null,
            note: null,
            createdAt: $movement->createdAt(),
            decrease: true,
        );

        self::assertTrue($copy->isDecrease());
    }

    public function testAnythingElseCannotBeADecrease(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->movement(MovementType::OUT, MovementReason::SALE_RETAIL, decrease: true);
    }

    public function testInternalUseAndReturnsToTheSupplierLeaveTheShelf(): void
    {
        self::assertFalse($this->movement(MovementType::OUT, MovementReason::CONSUMPTION_INTERNAL)->isDecrease());
        self::assertSame(MovementReason::RETURN_TO_SUPPLIER, $this->movement(MovementType::OUT, MovementReason::RETURN_TO_SUPPLIER)->reason());
    }

    private function movement(MovementType $type, MovementReason $reason, bool $decrease = false): StockMovement
    {
        $now = new \DateTimeImmutable('2026-08-21 10:00:00');

        return StockMovement::record(
            id: StockMovementId::fromString('01970003-0000-7000-8000-000000000001'),
            clinicId: ClinicId::fromString('550e8400-e29b-41d4-a716-446655440000'),
            articleId: ArticleId::fromString('550e8400-e29b-41d4-a716-446655440001'),
            lotId: null,
            type: $type,
            reason: $reason,
            quantity: Quantity::of('2.000000', UnitOfMeasure::fromString('UNIT')),
            occurredAt: $now,
            reference: null,
            performedBy: null,
            note: null,
            createdAt: $now,
            decrease: $decrease,
        );
    }
}
