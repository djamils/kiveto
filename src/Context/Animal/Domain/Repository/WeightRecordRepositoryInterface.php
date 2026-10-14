<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\Repository;

use App\Context\Animal\Domain\WeightRecord;

interface WeightRecordRepositoryInterface
{
    public function save(WeightRecord $record): void;
}
