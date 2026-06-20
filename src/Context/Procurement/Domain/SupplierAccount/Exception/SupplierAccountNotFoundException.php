<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierAccount\Exception;

final class SupplierAccountNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('SupplierAccount "%s" not found.', $id));
    }
}
