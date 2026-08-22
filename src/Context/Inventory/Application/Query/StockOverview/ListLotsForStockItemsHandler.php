<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListLotsForStockItemsHandler
{
    public function __construct(private StockOverviewReadRepositoryInterface $stock)
    {
    }

    /**
     * @return array<string, list<LotRow>> keyed by stock item id
     */
    public function __invoke(ListLotsForStockItems $query): array
    {
        if ([] === $query->stockItemIds) {
            return [];
        }

        return $this->stock->lotsFor(
            ClinicId::fromString($query->clinicId),
            $query->stockItemIds,
        );
    }
}
