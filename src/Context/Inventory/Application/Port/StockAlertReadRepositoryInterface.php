<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Port;

use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;

interface StockAlertReadRepositoryInterface
{
    /**
     * @return list<StockAlertView>
     */
    public function findActive(ClinicId $clinicId, ?StockAlertSeverity $severity): array;

    public function findByStockItemAndType(StockItemId $stockItemId, StockAlertType $type): ?StockAlertView;

    public function save(StockAlertView $alert): void;

    public function resolve(string $alertId, \DateTimeImmutable $resolvedAt): void;
}
