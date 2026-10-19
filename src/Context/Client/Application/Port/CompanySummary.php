<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

/**
 * A company as listed by the registry search, with its head office.
 */
final readonly class CompanySummary
{
    public function __construct(
        public string $siren,
        public string $name,
        public ?string $legalFormLabel,
        public ?string $nafCode,
        public bool $isAssociation,
        public bool $isActive,
        public ?string $ceasedOn,
        public ?string $headOfficeSiret,
        public ?string $addressLine,
        public ?string $postalCode,
        public ?string $city,
    ) {
    }
}
