<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Stock;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Inventory\Application\Command\ReceiveStock\ReceiveStock;
use App\Context\Procurement\Application\Command\ConfirmPurchaseOrder\ConfirmPurchaseOrder;
use App\Context\Procurement\Application\Command\CreateSupplierReceipt\CreateSupplierReceipt;
use App\Context\Procurement\Application\Command\ValidateSupplierReceipt\ValidateSupplierReceipt;
use App\Context\Procurement\Application\Query\OrderBook\ListOpenPurchaseOrders;
use App\Context\Procurement\Application\Query\OrderBook\ListSupplyOffers;
use App\Context\Procurement\Application\Query\OrderBook\OpenPurchaseOrder;
use App\Context\Procurement\Application\Query\OrderBook\SupplyOffer;
use App\Presentation\Clinic\ViewModel\Stock\StockArticles;
use App\Presentation\Clinic\ViewModel\Stock\StockEndpoint;
use App\Presentation\Clinic\ViewModel\Stock\StockItemResolver;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Réceptionner une livraison »: each line becomes a lot (or tops up the lot
 * of the same number), counted in packagings of the supplier and entered in
 * the article's unit. An article never received gets its stock item on the
 * way.
 *
 * When the delivery is an order taken over, the order learns what came:
 * confirmed if it was only sent, then a validated supplier receipt for its
 * lines (never more than what remained to deliver). Stock is entered here
 * once — Procurement's receipt records the delivery, it does not credit the
 * shelves a second time.
 */
#[Route(path: '/stock/receipts', name: 'clinic_stock_receive', methods: ['POST'])]
final readonly class ReceiveStockController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private StockEndpoint $endpoint,
        private StockItemResolver $items,
        private StockArticles $articles,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (null !== $refused = $this->endpoint->guard($request)) {
            return $refused;
        }

        $clinicId   = $this->endpoint->clinicId();
        $supplierId = $request->request->getString('supplier');
        $reference  = StockEndpoint::text($request, 'bl');
        $order      = $this->order($clinicId, $request->request->getString('order'));
        $articles   = $this->articles->byId($clinicId);
        $lines      = [];

        // No supplier: stock that came from elsewhere — what the clinic held
        // before Kiveto, mostly.
        if ('' !== $supplierId && 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $supplierId)) {
            return $this->endpoint->refuse('Choisissez le fournisseur.');
        }

        foreach (StockEndpoint::rows($request, 'lines') as $row) {
            $article = $articles[$row['art'] ?? ''] ?? null;
            $packs   = StockEndpoint::quantity($row['n'] ?? '');
            $lot     = mb_strtoupper($row['lot'] ?? '');
            $expiry  = \DateTimeImmutable::createFromFormat('!Y-m-d', $row['per'] ?? '');

            if (null === $article || null === $packs || 0 >= bccomp($packs, '0', 6) || '' === $lot) {
                return $this->endpoint->refuse('Chaque ligne demande un article, un n° de lot et une quantité.');
            }

            // A lot that arrives already out of date is a typo far more often
            // than a delivery — the year is the field people get wrong.
            if (false === $expiry || $expiry < $this->clock->now()->setTime(0, 0)) {
                return $this->endpoint->refuse(\sprintf('La date de péremption du lot %s est invalide ou déjà passée.', $lot));
            }

            $lines[] = ['article' => $article, 'packs' => $packs, 'lot' => $lot, 'expiry' => $expiry->format('Y-m-d'), 'pol' => $row['pol'] ?? ''];
        }

        if ([] === $lines) {
            return $this->endpoint->refuse('Ajoutez au moins une ligne.');
        }

        $offers = $this->offers($clinicId, $supplierId, array_map(static fn (array $line): ArticleListRow => $line['article'], $lines));
        $undo   = [];

        try {
            foreach ($lines as $line) {
                $article  = $line['article'];
                $item     = $this->items->findOrOpen($clinicId, $article->id);
                $before   = $this->lotQuantity($clinicId, $item['id'], $line['lot']);
                $perPack  = $offers[$article->id] ?? '1';
                $quantity = bcmul($line['packs'], $perPack, 6);

                $this->commandBus->dispatch(new ReceiveStock(
                    stockItemId: $item['id'],
                    clinicId: $clinicId,
                    lotNumber: $line['lot'],
                    quantityAmount: $quantity,
                    quantityUnit: $item['unit'],
                    expiryDate: $line['expiry'],
                    sourceSupplierId: '' !== $supplierId ? $supplierId : null,
                    occurredAt: null,
                    reference: $reference,
                    performedBy: $this->endpoint->performer(),
                ));

                $lotId = $this->lotId($clinicId, $item['id'], $line['lot']);

                if (null !== $lotId) {
                    $undo[] = ['item' => $item['id'], 'lot' => $lotId, 'q' => $before];
                }
            }

            if (null !== $order && $order->supplierId === $supplierId) {
                $this->deliver($clinicId, $order, $lines, $reference);
            }
        } catch (\LogicException $exception) {
            return $this->endpoint->refuse($exception);
        }

        // A delivery recorded against an order cannot be taken back: the
        // supplier receipt is validated. A free one can.
        return $this->endpoint->ok(['undo' => null === $order ? $undo : [], 'n' => \count($lines), 'bl' => $reference]);
    }

    private function order(string $clinicId, string $orderId): ?OpenPurchaseOrder
    {
        if ('' === $orderId) {
            return null;
        }

        $orders = $this->queryBus->ask(new ListOpenPurchaseOrders($clinicId));

        foreach (\is_array($orders) ? $orders : [] as $order) {
            if ($order instanceof OpenPurchaseOrder && $order->id === $orderId && !$order->isDraft()) {
                return $order;
            }
        }

        return null;
    }

    /**
     * How many units of each article one packaging holds: the delivering
     * supplier's packaging when it sells the article, else the one the page
     * shows — the article's usual offer.
     *
     * @param list<ArticleListRow> $articles
     *
     * @return array<string, numeric-string> keyed by article id
     */
    private function offers(string $clinicId, string $supplierId, array $articles): array
    {
        $offers = $this->queryBus->ask(new ListSupplyOffers(
            $clinicId,
            array_values(array_filter(array_map(static fn (ArticleListRow $a): string => $a->gtin ?? '', $articles))),
            array_map(static fn (ArticleListRow $a): string => $a->code, $articles),
        ));

        $offers = array_values(array_filter(\is_array($offers) ? $offers : [], static fn ($offer): bool => $offer instanceof SupplyOffer));
        $out    = [];

        foreach ($articles as $article) {
            $usual = null;

            foreach ($offers as $offer) {
                $byGtin  = null !== $article->gtin && $offer->gtin === $article->gtin;
                $matches = $byGtin || $offer->productCode === $article->code;

                if (!$matches) {
                    continue;
                }

                if ($offer->supplierId === $supplierId) {
                    $usual = $offer;

                    break;
                }

                // The page matches the barcode first, then the reference.
                if (null === $usual || ($byGtin && $usual->gtin !== $article->gtin)) {
                    $usual = $offer;
                }
            }

            $amount = null !== $usual ? StockEndpoint::quantity($usual->packagingAmount ?? '') : null;

            if (null !== $amount && 0 < bccomp($amount, '0', 6)) {
                $out[$article->id] = $amount;
            }
        }

        return $out;
    }

    /**
     * @param list<array{article: ArticleListRow, packs: numeric-string, lot: string, expiry: string, pol: string}> $lines
     */
    private function deliver(string $clinicId, OpenPurchaseOrder $order, array $lines, ?string $reference): void
    {
        if (!\in_array($order->status, ['SUBMITTED', 'CONFIRMED', 'PARTIALLY_RECEIVED'], true)) {
            return;
        }

        $receipt = [];

        foreach ($order->lines as $orderLine) {
            foreach ($lines as $line) {
                if ($line['pol'] !== $orderLine->id || 'ACTIVE' !== $orderLine->status) {
                    continue;
                }

                $ordered  = StockEndpoint::quantity($orderLine->orderedAmount) ?? '0';
                $received = StockEndpoint::quantity($orderLine->receivedAmount) ?? '0';
                $remains  = bcsub($ordered, $received, 6);
                $amount   = 0 > bccomp($line['packs'], $remains, 6) ? $line['packs'] : $remains;

                if (0 >= bccomp($amount, '0', 6)) {
                    continue;
                }

                $receipt[] = [
                    'purchaseOrderLineId'     => $orderLine->id,
                    'articleId'               => $orderLine->articleId,
                    'receivedAmount'          => $amount,
                    'receivedUnit'            => $orderLine->orderedUnit,
                    'lotNumber'               => $line['lot'],
                    'expiryDate'              => $line['expiry'],
                    'manufacturedAt'          => null,
                    'actualUnitPriceMinor'    => null,
                    'actualUnitPriceCurrency' => null,
                    'note'                    => null,
                ];
            }
        }

        if ([] === $receipt) {
            return;
        }

        if ('SUBMITTED' === $order->status) {
            $this->commandBus->dispatch(new ConfirmPurchaseOrder($order->id, $clinicId));
        }

        $receiptId = $this->commandBus->dispatch(new CreateSupplierReceipt(
            clinicId: $clinicId,
            supplierId: $order->supplierId,
            purchaseOrderId: $order->id,
            deliveryNoteReference: $reference ?? $order->orderNumber,
            matchType: 'MANUALLY_MATCHED',
            receivedBy: $this->endpoint->performer(),
            comment: null,
            lines: $receipt,
        ));

        \assert(\is_string($receiptId));

        $this->commandBus->dispatch(new ValidateSupplierReceipt($receiptId, $clinicId));
    }

    private function lotQuantity(string $clinicId, string $stockItemId, string $number): string
    {
        foreach ($this->items->lots($clinicId, $stockItemId) as $lot) {
            if ($lot->number === $number) {
                return $lot->quantity;
            }
        }

        return '0.000000';
    }

    private function lotId(string $clinicId, string $stockItemId, string $number): ?string
    {
        foreach ($this->items->lots($clinicId, $stockItemId) as $lot) {
            if ($lot->number === $number) {
                return $lot->id;
            }
        }

        return null;
    }
}
