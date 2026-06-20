<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\Exception;

final class SupplierReceiptNotFoundException extends \RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct(\sprintf('Supplier receipt "%s" not found.', $id));
    }
}
