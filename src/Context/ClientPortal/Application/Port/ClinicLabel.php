<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class ClinicLabel
{
    public function __construct(
        public string $name,
        public ?string $city,
    ) {
    }
}
