<?php

declare(strict_types=1);

namespace App\Context\Procurement\Application\Exception;

final class SupplierAccountDisabledException extends \RuntimeException
{
    public function __construct(string $accountId)
    {
        parent::__construct(\sprintf('Supplier account "%s" is disabled.', $accountId));
    }
}
