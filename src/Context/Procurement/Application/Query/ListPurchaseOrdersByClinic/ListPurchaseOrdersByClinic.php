<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ListPurchaseOrdersByClinic;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListPurchaseOrdersByClinic implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
