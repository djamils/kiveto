<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Repository;

use App\Context\Clinic\Domain\ClinicGroup;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupId;

interface ClinicGroupRepositoryInterface
{
    public function save(ClinicGroup $clinicGroup): void;

    public function findById(ClinicGroupId $id): ?ClinicGroup;
}
