<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ActivateClinicGroup;

final readonly class ActivateClinicGroup
{
    public function __construct(
        public string $clinicGroupId,
    ) {
    }
}
