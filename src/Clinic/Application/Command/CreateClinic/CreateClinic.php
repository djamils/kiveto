<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\CreateClinic;

final readonly class CreateClinic
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $timeZone,
        public string $locale,
        public ?string $clinicGroupId = null,
    ) {
    }
}
