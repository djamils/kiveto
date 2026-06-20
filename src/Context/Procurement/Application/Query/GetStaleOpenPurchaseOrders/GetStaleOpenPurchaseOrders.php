<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\GetStaleOpenPurchaseOrders;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetStaleOpenPurchaseOrders implements QueryInterface
{
    public function __construct(public int $daysThreshold = 60)
    {
    }
}
