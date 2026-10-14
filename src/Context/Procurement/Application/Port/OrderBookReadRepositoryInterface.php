<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Port;

use App\Context\Procurement\Application\Query\OrderBook\OpenPurchaseOrder;
use App\Context\Procurement\Application\Query\OrderBook\SupplyOffer;

/**
 * What the stock page reads of Procurement: who sells what to the clinic, and
 * the orders under way.
 */
interface OrderBookReadRepositoryInterface
{
    /**
     * Active entries of the active suppliers the clinic holds an active
     * account with, matched on a barcode or a supplier reference.
     *
     * @param list<string> $gtins
     * @param list<string> $productCodes
     *
     * @return list<SupplyOffer>
     */
    public function supplyOffers(string $clinicId, array $gtins, array $productCodes): array;

    /**
     * @return list<OpenPurchaseOrder> oldest first
     */
    public function openOrders(string $clinicId): array;
}
