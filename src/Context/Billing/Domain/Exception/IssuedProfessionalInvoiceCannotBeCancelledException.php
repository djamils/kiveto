<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\Exception;

/**
 * An issued professional invoice is never cancelled — it may already be on
 * its way to the tax authority: it is corrected by a credit note.
 */
final class IssuedProfessionalInvoiceCannotBeCancelledException extends \DomainException
{
    public function __construct(public readonly string $invoiceId)
    {
        parent::__construct(\sprintf('Issued professional invoice %s cannot be cancelled: issue a credit note.', $invoiceId));
    }
}
