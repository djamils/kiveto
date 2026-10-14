<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\OrderBook;

use App\Context\Procurement\Application\Port\OrderBookReadRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListOpenPurchaseOrdersHandler
{
    public function __construct(private OrderBookReadRepositoryInterface $orderBook)
    {
    }

    /**
     * @return list<OpenPurchaseOrder>
     */
    public function __invoke(ListOpenPurchaseOrders $query): array
    {
        return $this->orderBook->openOrders($query->clinicId);
    }
}
