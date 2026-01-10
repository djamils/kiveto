<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ChangeClinicTimeZone;

final readonly class ChangeClinicTimeZone
{
    public function __construct(
        public string $clinicId,
        public string $timeZone,
    ) {
    }
}
