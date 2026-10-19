<?php

declare(strict_types=1);

namespace App\Context\Billing\Application\Port;

/**
 * What the Client context knows of a client's tax identity and postal
 * address, as Billing freezes it on an invoice.
 */
final readonly class PayerFiscalSnapshotDto
{
    /**
     * @param list<string> $streetLines
     */
    public function __construct(
        public string $taxStatus,
        public ?string $legalName,
        public ?string $siren,
        public ?string $vatNumber,
        public array $streetLines,
        public ?string $postalCode,
        public ?string $city,
        public string $countryCode = 'FR',
    ) {
    }
}
