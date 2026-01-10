<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\GetClinicGroup;

final readonly class GetClinicGroup
{
    public function __construct(
        public string $clinicGroupId,
    ) {
    }
}
