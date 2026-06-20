<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\GetSupplierDetail;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetSupplierDetail implements QueryInterface
{
    public function __construct(public string $supplierId)
    {
    }
}
