<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetClinicStockHandler
{
    public function __construct(
        private StockOverviewReadRepositoryInterface $stock,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetClinicStock $query): ClinicStock
    {
        $clinicId = ClinicId::fromString($query->clinicId);
        $items    = $this->stock->snapshotsOfClinic($clinicId);

        $stockItemIds = array_values(array_map(
            static fn (StockSnapshotRow $item): string => $item->stockItemId,
            $items,
        ));

        return new ClinicStock(
            items: $items,
            lots: [] === $stockItemIds ? [] : $this->stock->lotsFor($clinicId, $stockItemIds),
            outflows: $this->stock->outflows($clinicId, $this->clock->now()->setTimezone(new \DateTimeZone($query->timeZone)), $query->weeks),
            movements: $this->stock->latestMovements($clinicId, $query->latestMovements),
            lastCountAt: $this->stock->lastCountAt($clinicId),
        );
    }
}
