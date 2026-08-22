<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\StockOverview;

use App\Context\Inventory\Application\Port\StockOverviewReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StockSnapshotForArticlesHandler
{
    public function __construct(private StockOverviewReadRepositoryInterface $stock)
    {
    }

    /**
     * @return array<string, StockSnapshotRow> keyed by article id
     */
    public function __invoke(StockSnapshotForArticles $query): array
    {
        if ([] === $query->articleIds) {
            return [];
        }

        return $this->stock->snapshotFor(
            ClinicId::fromString($query->clinicId),
            $query->articleIds,
        );
    }
}
