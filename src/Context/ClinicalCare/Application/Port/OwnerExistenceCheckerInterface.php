<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Application\Port;

use App\Context\ClinicalCare\Domain\ValueObject\OwnerId;

interface OwnerExistenceCheckerInterface
{
    public function exists(OwnerId $ownerId): bool;
}
