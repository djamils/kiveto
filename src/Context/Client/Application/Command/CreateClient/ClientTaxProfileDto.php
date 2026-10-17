<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Command\CreateClient;

/**
 * Raw tax profile as typed by the user: `status` is `individual` or
 * `french_business`; SIREN and VAT number are only read for the latter.
 */
final readonly class ClientTaxProfileDto
{
    public function __construct(
        public string $status,
        public ?string $legalName = null,
        public ?string $siren = null,
        public ?string $vatNumber = null,
    ) {
    }
}
