<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Command\RemoveSupplierPricing;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemoveSupplierPricing implements CommandInterface
{
    public function __construct(public string $pricingId)
    {
    }
}
