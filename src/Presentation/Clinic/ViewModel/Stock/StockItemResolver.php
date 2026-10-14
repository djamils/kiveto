<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Stock;

use App\Context\Catalog\Application\Query\Article\GetArticleDetail\ArticleDetailView;
use App\Context\Catalog\Application\Query\Article\GetArticleDetail\GetArticleDetail;
use App\Context\Inventory\Application\Command\OpenStockItem\OpenStockItem;
use App\Context\Inventory\Application\Query\GetStockItem\GetStockItem;
use App\Context\Inventory\Application\Query\GetStockItem\StockItemView;
use App\Context\Inventory\Application\Query\StockOverview\ListLotsForStockItems;
use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotForArticles;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The stock item behind a catalogue article. The page lists articles the
 * clinic follows but has never received: setting a threshold, a place or
 * receiving one opens its stock item on the way, rather than asking for a
 * « start following » step that only the model needs.
 */
final readonly class StockItemResolver
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
    ) {
    }

    public function find(string $clinicId, string $articleId): ?StockSnapshotRow
    {
        $snapshots = $this->queryBus->ask(new StockSnapshotForArticles($clinicId, [$articleId]));
        $snapshot  = \is_array($snapshots) ? ($snapshots[$articleId] ?? null) : null;

        return $snapshot instanceof StockSnapshotRow ? $snapshot : null;
    }

    /**
     * @return array{id: string, unit: string}
     */
    public function findOrOpen(string $clinicId, string $articleId): array
    {
        $existing = $this->find($clinicId, $articleId);

        if (null !== $existing) {
            return ['id' => $existing->stockItemId, 'unit' => $existing->unit];
        }

        $article = $this->queryBus->ask(new GetArticleDetail($articleId, $clinicId));
        \assert($article instanceof ArticleDetailView);

        $opened = $this->commandBus->dispatch(new OpenStockItem(
            clinicId: $clinicId,
            articleId: $articleId,
            unit: $article->unitOfMeasure,
            thresholdAmount: '0.000000',
            thresholdUnit: $article->unitOfMeasure,
            thresholdType: 'ABSOLUTE',
            trackStock: true,
        ));

        \assert(\is_string($opened));

        return ['id' => $opened, 'unit' => $article->unitOfMeasure];
    }

    /**
     * The lots of one stock item, by id.
     *
     * @return array<string, LotRow>
     */
    public function lots(string $clinicId, string $stockItemId): array
    {
        $lots = $this->queryBus->ask(new ListLotsForStockItems($clinicId, [$stockItemId]));
        $mine = \is_array($lots) ? ($lots[$stockItemId] ?? []) : [];
        $out  = [];

        foreach (\is_array($mine) ? $mine : [] as $lot) {
            if ($lot instanceof LotRow) {
                $out[$lot->id] = $lot;
            }
        }

        return $out;
    }

    /**
     * The unit a stock item counts in — a property of the item, never of the
     * request: a form that posted the wrong one would record a quantity in a
     * unit the shelf does not use.
     */
    public function unitOf(string $clinicId, string $stockItemId): string
    {
        $item = $this->queryBus->ask(new GetStockItem($stockItemId, $clinicId));
        \assert($item instanceof StockItemView);

        return $item->totalOnHandUnit;
    }
}
