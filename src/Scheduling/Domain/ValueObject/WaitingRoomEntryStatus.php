<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\ValueObject;

enum WaitingRoomEntryStatus: string
{
    case WAITING = 'WAITING';
    case CALLED = 'CALLED';
    case IN_SERVICE = 'IN_SERVICE';
    case CLOSED = 'CLOSED';

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::WAITING => \in_array($newStatus, [self::CALLED, self::IN_SERVICE, self::CLOSED], true),
            self::CALLED => \in_array($newStatus, [self::IN_SERVICE, self::CLOSED], true),
            self::IN_SERVICE => self::CLOSED === $newStatus,
            self::CLOSED => false,
        };
    }
}
