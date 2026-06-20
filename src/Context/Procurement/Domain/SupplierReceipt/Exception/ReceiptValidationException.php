<?php

declare(strict_types=1);

namespace App\Context\Procurement\Domain\SupplierReceipt\Exception;

final class ReceiptValidationException extends \LogicException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
