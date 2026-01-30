<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

enum WaitingRoomArrivalMode: string
{
    case STANDARD = 'STANDARD';
    case EMERGENCY = 'EMERGENCY';
}
