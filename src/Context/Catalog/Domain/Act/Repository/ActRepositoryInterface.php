<?php

declare(strict_types=1);

namespace App\Context\Catalog\Domain\Act\Repository;

use App\Context\Catalog\Domain\Act\Act;
use App\Context\Catalog\Domain\Act\ValueObject\ActCode;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;

interface ActRepositoryInterface
{
    public function save(Act $act): void;

    public function findById(ActId $id, ClinicId $clinicId): ?Act;

    public function findByCode(ActCode $code, ClinicId $clinicId): ?Act;

    public function existsByCode(ActCode $code, ClinicId $clinicId): bool;
}
