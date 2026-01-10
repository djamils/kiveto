<?php

declare(strict_types=1);

namespace App\Clinic\Application\Query\ListClinics;

use App\Clinic\Application\Query\GetClinic\ClinicDto;

final readonly class ClinicCollection
{
    /**
     * @param list<ClinicDto> $clinics
     */
    public function __construct(
        public array $clinics,
        public int $total,
    ) {
    }
}
