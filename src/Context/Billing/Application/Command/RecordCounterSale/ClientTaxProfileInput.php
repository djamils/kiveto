<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordCounterSale;

/**
 * The tax profile of a structure opened at the desk, as typed: `status` is
 * `individual` (not VAT-liable) or `french_business`. Checked by the counter
 * sale before the Client context opens the record.
 */
final readonly class ClientTaxProfileInput
{
    public const string INDIVIDUAL      = 'individual';
    public const string FRENCH_BUSINESS = 'french_business';

    public function __construct(
        public string $status,
        public ?string $legalName = null,
        public ?string $siren = null,
        public ?string $vatNumber = null,
    ) {
    }
}
