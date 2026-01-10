<?php

declare(strict_types=1);

namespace App\Clinic\Application\Port;

use App\Clinic\Application\Query\ListClinics\ClinicCollection;
use App\Clinic\Domain\ValueObject\ClinicStatus;

interface ClinicReadRepositoryInterface
{
    public function findAllFiltered(
        ?ClinicStatus $status = null,
        ?string $clinicGroupId = null,
        ?string $search = null,
    ): ClinicCollection;
}
