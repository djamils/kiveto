<?php

declare(strict_types=1);

namespace App\Context\Animal\Domain\Repository;

use App\Context\Animal\Domain\PreventiveCareRecord;

interface PreventiveCareRecordRepositoryInterface
{
    public function save(PreventiveCareRecord $record): void;
}
