<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The clinic's letterhead address: both street lines joined in `line`. */
final readonly class ClinicAddress
{
    public function __construct(
        public string $line,
        public ?string $postalCode,
        public string $city,
    ) {
    }
}
