<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\SuspendClinicGroup;

final readonly class SuspendClinicGroup
{
    public function __construct(
        public string $clinicGroupId,
    ) {
    }
}
