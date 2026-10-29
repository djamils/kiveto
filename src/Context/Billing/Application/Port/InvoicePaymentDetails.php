<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * What the clinic set about its invoices that is printed at their foot: the
 * account to pay by transfer and its free note.
 */
final readonly class InvoicePaymentDetails
{
    public function __construct(
        /** Grouped by four, as written on paper: "FR76 3000 6000 …". */
        public ?string $iban,
        public ?string $bic,
        public bool $vatOnDebits,
        public ?string $invoiceFooterNote,
    ) {
    }
}
