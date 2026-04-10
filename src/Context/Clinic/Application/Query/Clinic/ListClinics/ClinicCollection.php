<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Query\Clinic\ListClinics;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;

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
