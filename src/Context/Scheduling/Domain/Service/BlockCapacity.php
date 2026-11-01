<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Service;

/**
 * How many bookings a planning block occurrence holds (S4c): « 4 per hour »
 * on a 08:00–12:00 block holds 16. The window is the block's length in local
 * minutes, or the slot's when the slot is longer. 0 means unlimited.
 */
final class BlockCapacity
{
    public static function of(int $capacityPerHour, int $windowMinutes): int
    {
        return (int) floor($capacityPerHour * $windowMinutes / 60);
    }

    public static function isReached(int $capacityPerHour, int $windowMinutes, int $occupying): bool
    {
        $capacity = self::of($capacityPerHour, $windowMinutes);

        return $capacity > 0 && $occupying >= $capacity;
    }
}
