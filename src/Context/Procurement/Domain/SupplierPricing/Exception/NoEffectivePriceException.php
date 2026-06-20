<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierPricing\Exception;

final class NoEffectivePriceException extends \RuntimeException
{
    public function __construct(string $entryId)
    {
        parent::__construct(\sprintf('No effective price found for catalog entry "%s".', $entryId));
    }
}
