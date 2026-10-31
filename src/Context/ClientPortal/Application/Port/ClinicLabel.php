<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class ClinicLabel
{
    public function __construct(
        public string $name,
        public ?string $city,
        /** IANA time zone of the clinic: its clinical dates are shown in it. */
        public string $timeZone = 'UTC',
    ) {
    }
}
