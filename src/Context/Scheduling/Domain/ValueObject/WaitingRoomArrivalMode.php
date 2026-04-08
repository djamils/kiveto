<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\ValueObject;

enum WaitingRoomArrivalMode: string
{
    case STANDARD  = 'STANDARD';
    case EMERGENCY = 'EMERGENCY';
}
