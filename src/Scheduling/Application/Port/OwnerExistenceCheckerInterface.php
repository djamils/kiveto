<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Port;

use App\Scheduling\Domain\ValueObject\OwnerId;

interface OwnerExistenceCheckerInterface
{
    public function exists(OwnerId $ownerId): bool;
}
