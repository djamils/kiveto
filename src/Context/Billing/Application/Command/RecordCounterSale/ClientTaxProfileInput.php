<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Command\RecordCounterSale;

/**
 * The tax profile of a professional opened at the desk, as typed: `status`
 * is `individual` or `french_business`. The Client context checks it.
 */
final readonly class ClientTaxProfileInput
{
    public function __construct(
        public string $status,
        public ?string $legalName = null,
        public ?string $siren = null,
        public ?string $vatNumber = null,
    ) {
    }
}
