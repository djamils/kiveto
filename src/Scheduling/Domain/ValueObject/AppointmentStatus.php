<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

enum AppointmentStatus: string
{
    case PLANNED   = 'PLANNED';
    case CANCELLED = 'CANCELLED';
    case NO_SHOW   = 'NO_SHOW';
    case COMPLETED = 'COMPLETED';

    public function isTerminal(): bool
    {
        return \in_array($this, [self::CANCELLED, self::NO_SHOW, self::COMPLETED], true);
    }
}
