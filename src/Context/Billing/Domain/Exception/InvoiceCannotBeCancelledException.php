<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

final class InvoiceCannotBeCancelledException extends \DomainException
{
    public function __construct(string $invoiceId, string $reason)
    {
        parent::__construct(\sprintf('Invoice %s cannot be cancelled: %s', $invoiceId, $reason));
    }
}
