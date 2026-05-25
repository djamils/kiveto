<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

use App\Context\Inventory\Application\Query\GetExpiringLots\ExpiringLotView;
use App\Context\Inventory\Application\Query\GetStockItem\StockItemView;
use App\Context\Inventory\Application\Query\ListStockItemsByClinic\StockItemSummaryView;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemStatus;

interface StockItemReadRepositoryInterface
{
    public function findById(StockItemId $id, ClinicId $clinicId): ?StockItemView;

    /**
     * Returns pairs of [stockItemId, clinicId] for stock items that have at least one ACTIVE lot past its expiry date.
     *
     * @return list<array{stockItemId: string, clinicId: string}>
     */
    public function findItemsWithExpiredLots(?ClinicId $clinicId): array;

    /**
     * @return list<ExpiringLotView>
     */
    public function findLotsExpiringWithin(ClinicId $clinicId, int $days): array;

    /**
     * @return list<StockItemSummaryView>
     */
    public function findByClinicAndStatus(ClinicId $clinicId, ?StockItemStatus $status, int $limit, int $offset): array;
}
