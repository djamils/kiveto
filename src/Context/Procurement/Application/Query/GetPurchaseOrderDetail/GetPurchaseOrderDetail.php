<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\GetPurchaseOrderDetail;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetPurchaseOrderDetail implements QueryInterface
{
    public function __construct(
        public string $purchaseOrderId,
        public string $clinicId,
    ) {
    }
}
