<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\IsOnlineBookingOpen;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Whether the clinic takes online bookings now → bool: its settings are
 * enabled and the clinic is active (false for an unknown clinic). The same
 * rule refuses availability with BookingNotOpenException.
 */
final readonly class IsOnlineBookingOpen implements QueryInterface
{
    public function __construct(public string $clinicId)
    {
    }
}
