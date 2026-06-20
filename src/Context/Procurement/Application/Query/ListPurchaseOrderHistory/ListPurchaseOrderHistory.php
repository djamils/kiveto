<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ListPurchaseOrderHistory;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListPurchaseOrderHistory implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
