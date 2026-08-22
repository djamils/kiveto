<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

final class InvoiceNotFoundException extends \DomainException
{
    public function __construct(string $invoiceId)
    {
        parent::__construct(\sprintf('Invoice not found: %s', $invoiceId));
    }
}
