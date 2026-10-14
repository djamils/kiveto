<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\OrderBook;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListOpenPurchaseOrders implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
