<?php

declare(strict_types=1);

namespace App\Context\Clinic\Domain\Repository;

use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Shared\Domain\ValueObject\Siren;

interface ClinicRepositoryInterface
{
    public function save(Clinic $clinic): void;

    public function findById(ClinicId $id): ?Clinic;

    public function findBySlug(ClinicSlug $slug): ?Clinic;

    public function existsBySlug(ClinicSlug $slug): bool;

    /**
     * The other establishments of the same company: every clinic but this one
     * whose letterhead carries a valid SIRET of that SIREN.
     *
     * @return list<Clinic>
     */
    public function findOthersWithSiren(Siren $siren, ClinicId $excluding): array;
}
