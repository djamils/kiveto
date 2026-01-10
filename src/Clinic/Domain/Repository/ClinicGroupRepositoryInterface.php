<?php

declare(strict_types=1);

namespace App\Clinic\Domain\Repository;

use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\ValueObject\ClinicGroupId;

interface ClinicGroupRepositoryInterface
{
    public function save(ClinicGroup $clinicGroup): void;

    public function findById(ClinicGroupId $id): ?ClinicGroup;
}
