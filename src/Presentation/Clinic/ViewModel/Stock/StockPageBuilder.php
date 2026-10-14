<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Stock;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogListCriteria;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\CatalogPage;
use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ListArticles;
use App\Context\Inventory\Application\Query\StockOverview\ClinicStock;
use App\Context\Inventory\Application\Query\StockOverview\GetClinicStock;
use App\Context\Inventory\Application\Query\StockOverview\LotRow;
use App\Context\Inventory\Application\Query\StockOverview\MovementRow;
use App\Context\Inventory\Application\Query\StockOverview\StockSnapshotRow;
use App\Context\Inventory\Application\Query\WatchStock\StockWatchKind;
use App\Context\Inventory\Application\Query\WatchStock\StockWatchRow;
use App\Context\Inventory\Application\Query\WatchStock\WatchStock;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Procurement\Application\Query\ListSupplierAccountsByClinic\ListSupplierAccountsByClinic;
use App\Context\Procurement\Application\Query\ListSuppliers\ListSuppliers;
use App\Context\Procurement\Application\Query\OrderBook\ListOpenPurchaseOrders;
use App\Context\Procurement\Application\Query\OrderBook\ListSupplyOffers;
use App\Context\Procurement\Application\Query\OrderBook\OpenPurchaseOrder;
use App\Context\Procurement\Application\Query\OrderBook\SupplyOffer;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Everything the « Stocks » page module starts from (stock-v-topbar_3): every
 * article the clinic follows or could follow, with its lots, its outflows
 * week by week and its latest movements (Inventory), the supplier that sells
 * it and at what price (Procurement), the points of attention of the day, and
 * the orders under way.
 *
 * A clinic follows a few dozen articles: the list travels whole and the page
 * filters, sorts and pages it in the browser, like the mock-up, then asks
 * clinic_stock_data again after every change. The movement trail is the one
 * part that grows without bound, so it is paged by clinic_stock_movements_data.
 *
 * Quantities travel in the article's unit of measure; a supplier's packaging
 * (`cont` units per `cond`) is how they are ordered and received.
 */
final readonly class StockPageBuilder
{
    public const string CSRF_ID = 'stock';

    public const string NIL = '00000000-0000-7000-8000-000000000000';

    private const int WHOLE = 5000;

    private const array KINDS = ['DRUG' => 'med', 'CONSUMABLE' => 'conso', 'FOOD' => 'ali', 'EQUIPMENT' => 'mat'];

    private const array LOT_STATES = ['ACTIVE' => 'actif', 'DEPLETED' => 'epuise', 'RECALLED' => 'rappel', 'EXPIRED' => 'perime'];

    private const array SEVERITIES = ['CRITICAL' => 'crit', 'WARNING' => 'surv', 'INFO' => 'info'];

    public function __construct(
        private QueryBusInterface $queryBus,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $csrf,
        private StockMovementPresenter $movements,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function page(string $clinicId, string $tab): array
    {
        return [
            'tab'   => $tab,
            'token' => $this->csrf->getToken(self::CSRF_ID)->getValue(),
            'urls'  => $this->urls(),
        ] + $this->data($clinicId);
    }

    /**
     * The stock itself — what the page asks again after a change.
     *
     * @return array{items: list<array<string, mixed>>, alerts: list<array<string, mixed>>, orders: array{draft: list<array<string, mixed>>, sent: list<array<string, mixed>>}, suppliers: list<array{id: string, nom: string}>, dernierInv: string|null}
     */
    public function data(string $clinicId): array
    {
        $stock = $this->queryBus->ask(new GetClinicStock($clinicId));
        \assert($stock instanceof ClinicStock);

        $articles = $this->articles($clinicId, $stock);
        $offers   = $this->offers($clinicId, $articles);
        $staff    = $this->movements->staff($clinicId);
        $items    = [];

        usort($articles, static fn (ArticleListRow $a, ArticleListRow $b): int => strcmp($a->name, $b->name));

        foreach ($articles as $article) {
            $snapshot = $stock->items[$article->id] ?? null;
            $items[]  = $this->item($article, $snapshot, $stock, $offers[$article->id] ?? null, $staff);
        }

        return [
            'items'      => $items,
            'alerts'     => $this->alerts($clinicId),
            'orders'     => $this->orders($clinicId),
            'suppliers'  => $this->suppliers($clinicId),
            'dernierInv' => $stock->lastCountAt?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function urls(): array
    {
        $nil = self::NIL;

        return [
            'data'          => $this->urls->generate('clinic_stock_data'),
            'movements'     => $this->urls->generate('clinic_stock_movements_data'),
            'register'      => $this->urls->generate('clinic_stock_register_pdf'),
            'catalog'       => $this->urls->generate('clinic_catalogue_articles'),
            'directory'     => $this->urls->generate('clinic_clients_list'),
            'tabEtat'       => $this->urls->generate('clinic_stock'),
            'tabMvts'       => $this->urls->generate('clinic_stock_movements'),
            'tabAlertes'    => $this->urls->generate('clinic_stock_alerts'),
            'threshold'     => $this->urls->generate('clinic_stock_threshold', ['articleId' => $nil]),
            'location'      => $this->urls->generate('clinic_stock_location', ['articleId' => $nil]),
            'consume'       => $this->urls->generate('clinic_stock_consume', ['stockItemId' => $nil]),
            'discard'       => $this->urls->generate('clinic_stock_lot_discard', ['stockItemId' => $nil, 'lotId' => $nil]),
            'recall'        => $this->urls->generate('clinic_stock_lot_recall', ['stockItemId' => $nil, 'lotId' => $nil]),
            'count'         => $this->urls->generate('clinic_stock_count'),
            'restore'       => $this->urls->generate('clinic_stock_lots_restore'),
            'receive'       => $this->urls->generate('clinic_stock_receive'),
            'archive'       => $this->urls->generate('clinic_stock_archive', ['stockItemId' => $nil]),
            'unarchive'     => $this->urls->generate('clinic_stock_restore', ['stockItemId' => $nil]),
            'orderAdd'      => $this->urls->generate('clinic_stock_order_add'),
            'orderLine'     => $this->urls->generate('clinic_stock_order_line_update', ['purchaseOrderId' => $nil, 'lineId' => $nil]),
            'orderLineDrop' => $this->urls->generate('clinic_stock_order_line_remove', ['purchaseOrderId' => $nil, 'lineId' => $nil]),
            'orderSend'     => $this->urls->generate('clinic_stock_order_submit', ['purchaseOrderId' => $nil]),
            'orderFile'     => $this->urls->generate('clinic_stock_order_file', ['purchaseOrderId' => $nil]),
        ];
    }

    /**
     * The articles of the page: what the catalogue says to follow, and anything
     * Inventory already holds stock for — an article archived in the catalogue
     * or no longer followed still has its history.
     *
     * @return list<ArticleListRow>
     */
    private function articles(string $clinicId, ClinicStock $stock): array
    {
        $page = $this->queryBus->ask(new ListArticles($clinicId, new CatalogListCriteria(status: null, perPage: self::WHOLE)));

        if (!$page instanceof CatalogPage) {
            return [];
        }

        $articles = [];

        foreach ($page->items as $article) {
            if (!$article instanceof ArticleListRow) {
                continue;
            }

            if (isset($stock->items[$article->id]) || ($article->trackStock && !$article->isArchived())) {
                $articles[] = $article;
            }
        }

        return $articles;
    }

    /**
     * The offer each article is bought from: the one matched on its barcode,
     * else on its reference.
     *
     * @param list<ArticleListRow> $articles
     *
     * @return array<string, SupplyOffer> keyed by article id
     */
    private function offers(string $clinicId, array $articles): array
    {
        $gtins = array_values(array_filter(array_map(static fn (ArticleListRow $a): string => $a->gtin ?? '', $articles)));
        $codes = array_map(static fn (ArticleListRow $a): string => $a->code, $articles);

        $offers = $this->queryBus->ask(new ListSupplyOffers($clinicId, $gtins, $codes));

        $byGtin = [];
        $byCode = [];

        foreach (\is_array($offers) ? $offers : [] as $offer) {
            if (!$offer instanceof SupplyOffer) {
                continue;
            }

            if (null !== $offer->gtin) {
                $byGtin[$offer->gtin] ??= $offer;
            }

            $byCode[$offer->productCode] ??= $offer;
        }

        $out = [];

        foreach ($articles as $article) {
            $offer = (null !== $article->gtin ? ($byGtin[$article->gtin] ?? null) : null) ?? ($byCode[$article->code] ?? null);

            if (null !== $offer) {
                $out[$article->id] = $offer;
            }
        }

        return $out;
    }

    /**
     * @param array<string, array{n: string, ini: string, c: string}> $staff
     *
     * @return array<string, mixed>
     */
    private function item(ArticleListRow $article, ?StockSnapshotRow $snapshot, ClinicStock $stock, ?SupplyOffer $offer, array $staff): array
    {
        $lots    = null !== $snapshot ? ($stock->lots[$snapshot->stockItemId] ?? []) : [];
        $outflow = $stock->outflows[$article->id] ?? null;

        return [
            'id'      => $snapshot?->stockItemId,
            'art'     => $article->id,
            'code'    => $article->code,
            'lib'     => $article->name,
            'type'    => self::KINDS[$article->kind] ?? 'conso',
            'u'       => $snapshot->unit ?? $article->unitOfMeasure,
            'empl'    => $snapshot?->location,
            'stup'    => 'RX_NARCOTIC' === $article->prescriptionClass,
            'gtin'    => $article->gtin ?? '',
            'seuil'   => null !== $snapshot ? (float) $snapshot->threshold : 0.0,
            'suivi'   => null === $snapshot ? $article->trackStock : $snapshot->trackStock,
            'arch'    => null !== $snapshot && $snapshot->isArchived(),
            'catArch' => $article->isArchived(),
            'lots'    => array_map($this->lot(...), $lots),
            'conso'   => null !== $outflow ? (float) $outflow->usedLastThirtyDays : 0.0,
            'spark'   => null !== $outflow ? array_map('floatval', $outflow->weekly) : [],
            'mv'      => array_map(
                fn (MovementRow $row): array => $this->movements->row($row, $staff),
                $stock->movements[$article->id] ?? [],
            ),
            'fourn' => null === $offer ? null : [
                'id'     => $offer->supplierId,
                'nom'    => $offer->supplierName,
                'entree' => $offer->catalogEntryId,
                'prix'   => $offer->priceMinor,
                'cond'   => $offer->packagingUnit ?? ($snapshot->unit ?? $article->unitOfMeasure),
                'cont'   => null !== $offer->packagingAmount && (float) $offer->packagingAmount > 0 ? (float) $offer->packagingAmount : 1.0,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lot(LotRow $lot): array
    {
        return [
            'id'     => $lot->id,
            'n'      => $lot->number,
            'per'    => $lot->expiryDate,
            'q'      => (float) $lot->quantity,
            'statut' => self::LOT_STATES[$lot->status] ?? 'actif',
            'recu'   => null !== $lot->receivedAt ? mb_substr($lot->receivedAt, 0, 10) : null,
            'bl'     => $lot->reference,
            'rappel' => 'RECALLED' === $lot->status ? [
                'd'     => null !== $lot->changedAt ? mb_substr($lot->changedAt, 0, 10) : null,
                'motif' => $lot->recallReason,
            ] : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function alerts(string $clinicId): array
    {
        $rows = $this->queryBus->ask(new WatchStock($clinicId));
        $out  = [];

        foreach (\is_array($rows) ? $rows : [] as $row) {
            if (!$row instanceof StockWatchRow) {
                continue;
            }

            $out[] = [
                'g' => self::SEVERITIES[$row->severity->value],
                'k' => match ($row->kind) {
                    StockWatchKind::EXPIRED_LOT     => 'perime',
                    StockWatchKind::OUT_OF_STOCK    => 'rup',
                    StockWatchKind::BELOW_THRESHOLD => 'bas',
                    StockWatchKind::EXPIRING_LOT    => 'perp',
                    StockWatchKind::RECALLED_LOT    => 'rappel',
                },
                'art'  => $row->articleId,
                'lot'  => $row->lotId,
                'n'    => $row->lotNumber,
                'per'  => $row->expiryDate,
                'j'    => $row->days,
                'q'    => (float) $row->quantity,
                'crit' => StockAlertSeverity::CRITICAL === $row->severity,
            ];
        }

        return $out;
    }

    /**
     * The order being filled with each supplier (the drafts: one per supplier,
     * the page's « Commande en cours ») and the ones sent and not delivered.
     *
     * @return array{draft: list<array<string, mixed>>, sent: list<array<string, mixed>>}
     */
    private function orders(string $clinicId): array
    {
        $orders = $this->queryBus->ask(new ListOpenPurchaseOrders($clinicId));
        $draft  = [];
        $sent   = [];

        foreach (\is_array($orders) ? $orders : [] as $order) {
            if (!$order instanceof OpenPurchaseOrder) {
                continue;
            }

            $lines = [];
            $total = 0;

            foreach ($order->lines as $line) {
                if ('CANCELLED' === $line->status) {
                    continue;
                }

                $lines[] = [
                    'id'    => $line->id,
                    'art'   => $line->articleId,
                    'n'     => (float) $line->orderedAmount,
                    'recu'  => (float) $line->receivedAmount,
                    'unite' => $line->orderedUnit,
                    'prix'  => $line->unitPriceMinor,
                ];
                $total += (int) round((float) $line->orderedAmount * $line->unitPriceMinor);
            }

            $entry = [
                'id'      => $order->id,
                'numero'  => $order->orderNumber,
                'fourn'   => ['id' => $order->supplierId, 'nom' => $order->supplierName],
                'statut'  => $order->status,
                'd'       => mb_substr($order->submittedAt ?? $order->createdAt, 0, 10),
                'lignes'  => $lines,
                'tot'     => $total,
                'fichier' => null !== $order->exportFile,
            ];

            if ($order->isDraft()) {
                if ([] !== $lines) {
                    $draft[] = $entry;
                }

                continue;
            }

            $sent[] = $entry;
        }

        return ['draft' => $draft, 'sent' => $sent];
    }

    /**
     * The suppliers the clinic holds an active account with, for the
     * reception window.
     *
     * @return list<array{id: string, nom: string}>
     */
    private function suppliers(string $clinicId): array
    {
        $accounts  = $this->queryBus->ask(new ListSupplierAccountsByClinic($clinicId));
        $suppliers = $this->queryBus->ask(new ListSuppliers());
        $names     = [];

        foreach (\is_array($suppliers) ? $suppliers : [] as $supplier) {
            if (\is_array($supplier) && \is_string($supplier['id'] ?? null) && \is_string($supplier['name'] ?? null)) {
                $names[$supplier['id']] = $supplier['name'];
            }
        }

        $out = [];

        foreach (\is_array($accounts) ? $accounts : [] as $account) {
            $supplierId = \is_array($account) ? ($account['supplierId'] ?? null) : null;

            if (\is_string($supplierId) && \is_array($account) && 'ACTIVE' === ($account['status'] ?? null) && isset($names[$supplierId])) {
                $out[$supplierId] = ['id' => $supplierId, 'nom' => $names[$supplierId]];
            }
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['nom'], $b['nom']));

        return $out;
    }
}
