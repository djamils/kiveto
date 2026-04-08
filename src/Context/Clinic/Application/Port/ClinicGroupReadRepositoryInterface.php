<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

use App\Context\Clinic\Application\Query\ListClinicGroups\ClinicGroupCollection;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupStatus;

interface ClinicGroupReadRepositoryInterface
{
    public function findAllFiltered(?ClinicGroupStatus $status = null): ClinicGroupCollection;
}
