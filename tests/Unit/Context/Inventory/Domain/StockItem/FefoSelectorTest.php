<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Inventory\Domain\StockItem;

use App\Context\Inventory\Domain\StockItem\Entity\Lot;
use App\Context\Inventory\Domain\StockItem\Service\FefoSelector;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotId;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotNumber;
use App\Context\Inventory\Domain\StockItem\ValueObject\LotStatus;
use App\Context\Inventory\Domain\StockItem\ValueObject\Quantity;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use PHPUnit\Framework\TestCase;

final class FefoSelectorTest extends TestCase
{
    private FefoSelector $selector;
    private UnitOfMeasure $unit;

    protected function setUp(): void
    {
        $this->selector = new FefoSelector();
        $this->unit     = UnitOfMeasure::fromString('UNIT');
    }

    public function testSelectOrdersLotsByExpiryDateAscending(): void
    {
        $lotA = $this->makeLot('01970001-0000-7000-8000-000000000001', 'LOT-A', '2026-08-01');
        $lotB = $this->makeLot('01970001-0000-7000-8000-000000000002', 'LOT-B', '2026-06-01');
        $lotC = $this->makeLot('01970001-0000-7000-8000-000000000003', 'LOT-C', '2026-10-01');

        $result = $this->selector->selectOrderedForConsumption([$lotA, $lotB, $lotC]);

        self::assertCount(3, $result);
        self::assertSame('LOT-B', $result[0]->number()->toString());
        self::assertSame('LOT-A', $result[1]->number()->toString());
        self::assertSame('LOT-C', $result[2]->number()->toString());
    }

    public function testSelectFiltersBothExpiredAndRecalledAndDepleted(): void
    {
        $active   = $this->makeLot('01970001-0000-7000-8000-000000000001', 'LOT-ACTIVE', '2026-12-01');
        $expired  = $this->makeLot('01970001-0000-7000-8000-000000000002', 'LOT-EXPIRED', '2026-01-01', LotStatus::EXPIRED);
        $recalled = $this->makeLot('01970001-0000-7000-8000-000000000003', 'LOT-RECALLED', '2026-12-01', LotStatus::RECALLED);
        $depleted = $this->makeLot('01970001-0000-7000-8000-000000000004', 'LOT-DEPLETED', '2026-12-01', LotStatus::DEPLETED);

        $result = $this->selector->selectOrderedForConsumption([$active, $expired, $recalled, $depleted]);

        self::assertCount(1, $result);
        self::assertSame('LOT-ACTIVE', $result[0]->number()->toString());
    }

    public function testSelectTieBreaksByReceivedAtAscending(): void
    {
        $lotA = $this->makeLot('01970001-0000-7000-8000-000000000001', 'LOT-A', '2026-06-01', LotStatus::ACTIVE, new \DateTimeImmutable('2026-01-10'));
        $lotB = $this->makeLot('01970001-0000-7000-8000-000000000002', 'LOT-B', '2026-06-01', LotStatus::ACTIVE, new \DateTimeImmutable('2026-01-05'));
        $lotC = $this->makeLot('01970001-0000-7000-8000-000000000003', 'LOT-C', '2026-06-01', LotStatus::ACTIVE, new \DateTimeImmutable('2026-01-20'));

        $result = $this->selector->selectOrderedForConsumption([$lotA, $lotB, $lotC]);

        self::assertCount(3, $result);
        self::assertSame('LOT-B', $result[0]->number()->toString());
        self::assertSame('LOT-A', $result[1]->number()->toString());
        self::assertSame('LOT-C', $result[2]->number()->toString());
    }

    public function testSelectReturnsEmptyArrayForEmptyInput(): void
    {
        $result = $this->selector->selectOrderedForConsumption([]);

        self::assertSame([], $result);
    }

    public function testSelectReturnsEmptyArrayWhenAllLotsNonActive(): void
    {
        $expired  = $this->makeLot('01970001-0000-7000-8000-000000000001', 'LOT-1', '2026-01-01', LotStatus::EXPIRED);
        $recalled = $this->makeLot('01970001-0000-7000-8000-000000000002', 'LOT-2', '2026-01-01', LotStatus::RECALLED);
        $depleted = $this->makeLot('01970001-0000-7000-8000-000000000003', 'LOT-3', '2026-01-01', LotStatus::DEPLETED);

        $result = $this->selector->selectOrderedForConsumption([$expired, $recalled, $depleted]);

        self::assertSame([], $result);
    }

    private function makeLot(
        string $id,
        string $number,
        string $expiryDate,
        LotStatus $status = LotStatus::ACTIVE,
        ?\DateTimeImmutable $receivedAt = null,
    ): Lot {
        return new Lot(
            id: LotId::fromString($id),
            number: LotNumber::fromString($number),
            quantity: Quantity::of('10.000000', $this->unit),
            expiryDate: new \DateTimeImmutable($expiryDate),
            receivedAt: $receivedAt,
            sourceSupplier: null,
            status: $status,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            updatedAt: new \DateTimeImmutable('2026-01-01'),
        );
    }
}
