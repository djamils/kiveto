<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

enum WaitingRoomEntryOrigin: string
{
    case SCHEDULED = 'SCHEDULED';
    case WALK_IN   = 'WALK_IN';
}
