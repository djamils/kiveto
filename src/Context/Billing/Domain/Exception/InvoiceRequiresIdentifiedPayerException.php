<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

/**
 * A visit is invoiced to a client: an unidentified patient (an emergency
 * brought in by a passer-by, a stray) is identified before it is cashed.
 */
final class InvoiceRequiresIdentifiedPayerException extends \DomainException
{
    public function __construct(string $invoiceId)
    {
        parent::__construct(\sprintf('Invoice %s belongs to a visit without an identified client and cannot be issued.', $invoiceId));
    }
}
