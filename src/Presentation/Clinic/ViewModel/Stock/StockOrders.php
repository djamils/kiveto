<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Stock;

use App\Context\Catalog\Application\Query\Catalog\ListCatalog\ArticleListRow;
use App\Context\Procurement\Application\Query\OrderBook\ListOpenPurchaseOrders;
use App\Context\Procurement\Application\Query\OrderBook\ListSupplyOffers;
use App\Context\Procurement\Application\Query\OrderBook\OpenPurchaseOrder;
use App\Context\Procurement\Application\Query\OrderBook\SupplyOffer;
use App\Shared\Application\Bus\QueryBusInterface;

/**
 * The clinic's side of its purchase orders, as the « Commande » drawer works
 * them: the order being filled with a supplier, the offer an article is
 * bought from.
 */
final readonly class StockOrders
{
    public function __construct(private QueryBusInterface $queryBus)
    {
    }

    /**
     * An order of this clinic that is still open — never another clinic's,
     * whatever id the page sent.
     */
    public function open(string $clinicId, string $orderId): ?OpenPurchaseOrder
    {
        foreach ($this->all($clinicId) as $order) {
            if ($order->id === $orderId) {
                return $order;
            }
        }

        return null;
    }

    /** The order being filled with a supplier, if there is one. */
    public function draftFor(string $clinicId, string $supplierId): ?OpenPurchaseOrder
    {
        foreach ($this->all($clinicId) as $order) {
            if ($order->isDraft() && $order->supplierId === $supplierId) {
                return $order;
            }
        }

        return null;
    }

    public function offer(string $clinicId, ArticleListRow $article): ?SupplyOffer
    {
        $offers = $this->queryBus->ask(new ListSupplyOffers($clinicId, null !== $article->gtin ? [$article->gtin] : [], [$article->code]));
        $byCode = null;

        foreach (\is_array($offers) ? $offers : [] as $offer) {
            if (!$offer instanceof SupplyOffer) {
                continue;
            }

            if (null !== $article->gtin && $offer->gtin === $article->gtin) {
                return $offer;
            }

            $byCode ??= $offer->productCode === $article->code ? $offer : null;
        }

        return $byCode;
    }

    /**
     * @return list<OpenPurchaseOrder>
     */
    private function all(string $clinicId): array
    {
        $orders = $this->queryBus->ask(new ListOpenPurchaseOrders($clinicId));

        return array_values(array_filter(\is_array($orders) ? $orders : [], static fn ($order): bool => $order instanceof OpenPurchaseOrder));
    }
}
