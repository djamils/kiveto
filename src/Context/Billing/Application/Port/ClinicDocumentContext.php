<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

use App\Shared\Application\Document\DocumentClinic;

/**
 * The clinic as an invoice prints it. Both parts are null for a clinic the
 * Clinic context does not know.
 */
final readonly class ClinicDocumentContext
{
    public function __construct(
        public ?DocumentClinic $clinic,
        public ?InvoicePaymentDetails $payment,
    ) {
    }
}
