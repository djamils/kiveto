<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierPricing\Exception;

final class SupplierPricingNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('SupplierPricing "%s" not found.', $id));
    }
}
