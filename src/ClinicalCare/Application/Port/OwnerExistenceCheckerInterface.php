<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Port;

use App\ClinicalCare\Domain\ValueObject\OwnerId;

interface OwnerExistenceCheckerInterface
{
    public function exists(OwnerId $ownerId): bool;
}
