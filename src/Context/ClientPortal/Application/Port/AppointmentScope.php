<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * `upcoming`: holding a slot and starting now or later, soonest first;
 * `past`: every other appointment, latest first.
 */
enum AppointmentScope: string
{
    case UPCOMING = 'upcoming';
    case PAST     = 'past';
}
