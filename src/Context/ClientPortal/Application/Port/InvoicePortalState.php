<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class InvoicePortalState
{
    public const string UNPAID         = 'unpaid';
    public const string PARTIALLY_PAID = 'partially_paid';
    public const string PAID           = 'paid';
    public const string CREDITED       = 'credited';

    /**
     * @param ?string $paymentStatus one of the constants, null when the invoice is no longer listable
     */
    public function __construct(
        public ?string $paymentStatus,
        public int $totalMinorUnits,
        public string $currency,
    ) {
    }
}
