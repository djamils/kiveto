<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Domain\Repository;

use App\System\PharmaceuticalRegistry\Domain\ActiveSubstance;

interface ActiveSubstanceRepositoryInterface
{
    public function save(ActiveSubstance $substance): void;

    public function findByNormalizedLabel(string $normalizedLabel): ?ActiveSubstance;
}
