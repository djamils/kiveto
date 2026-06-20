<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Query\GetSupplierPricingForClinic;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetSupplierPricingForClinic implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $supplierId,
    ) {
    }
}
