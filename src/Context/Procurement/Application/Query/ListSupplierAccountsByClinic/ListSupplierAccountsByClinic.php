<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\ListSupplierAccountsByClinic;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListSupplierAccountsByClinic implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
