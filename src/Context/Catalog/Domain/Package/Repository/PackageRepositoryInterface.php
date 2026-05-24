<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Package\Repository;

use App\Context\Catalog\Domain\Package\Package;
use App\Context\Catalog\Domain\Package\ValueObject\PackageCode;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;

interface PackageRepositoryInterface
{
    public function save(Package $package): void;

    public function findById(PackageId $id, ClinicId $clinicId): ?Package;

    public function findByCode(PackageCode $code, ClinicId $clinicId): ?Package;

    public function existsByCode(PackageCode $code, ClinicId $clinicId): bool;
}
