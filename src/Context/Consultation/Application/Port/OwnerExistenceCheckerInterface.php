<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

use App\Context\Consultation\Domain\ValueObject\OwnerId;

interface OwnerExistenceCheckerInterface
{
    public function exists(OwnerId $ownerId): bool;
}
