<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\OwnerId;

interface OwnerExistenceCheckerInterface
{
    public function exists(OwnerId $ownerId): bool;
}
