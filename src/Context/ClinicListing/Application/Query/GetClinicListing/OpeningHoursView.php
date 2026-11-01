<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Application\Query\GetClinicListing;

/** One opening slot of the week: ISO day (1 = Monday), local `HH:MM` times. */
final readonly class OpeningHoursView
{
    public function __construct(
        public int $day,
        public string $opensAt,
        public string $closesAt,
    ) {
    }
}
