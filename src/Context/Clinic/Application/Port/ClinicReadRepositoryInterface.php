<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

use App\Context\Clinic\Application\Query\Clinic\ListClinics\ClinicCollection;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;

interface ClinicReadRepositoryInterface
{
    public function findAllFiltered(
        ?ClinicStatus $status = null,
        ?string $clinicGroupId = null,
        ?string $search = null,
    ): ClinicCollection;
}
