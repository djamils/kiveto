<?php

declare(strict_types=1);

namespace App\System\Taxation\Domain\Repository;

use App\System\Taxation\Domain\TaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;

interface TaxRegimeRepositoryInterface
{
    public function save(TaxRegime $regime): void;

    public function findById(TaxRegimeId $id): ?TaxRegime;

    /** @return list<TaxRegime> */
    public function findAllActive(): array;
}
