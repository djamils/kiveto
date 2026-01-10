<?php

declare(strict_types=1);

namespace App\Clinic\Application\Port;

use App\Clinic\Application\Query\ListClinicGroups\ClinicGroupCollection;
use App\Clinic\Domain\ValueObject\ClinicGroupStatus;

interface ClinicGroupReadRepositoryInterface
{
    public function findAllFiltered(?ClinicGroupStatus $status = null): ClinicGroupCollection;
}
