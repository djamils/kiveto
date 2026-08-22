<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

final class InvoiceHasNoLinesException extends \DomainException
{
    public function __construct(string $invoiceId)
    {
        parent::__construct(\sprintf('Invoice %s cannot be issued without a single line.', $invoiceId));
    }
}
