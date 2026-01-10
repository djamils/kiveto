<?php

declare(strict_types=1);

namespace App\Clinic\Domain\Repository;

use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;

interface ClinicRepositoryInterface
{
    public function save(Clinic $clinic): void;

    public function findById(ClinicId $id): ?Clinic;

    public function findBySlug(ClinicSlug $slug): ?Clinic;

    public function existsBySlug(ClinicSlug $slug): bool;
}
