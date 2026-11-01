<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForOwners;

/**
 * `upcoming`: holding a slot and starting now or later, soonest first.
 * `past`: every other appointment, latest first.
 */
enum AppointmentListScope: string
{
    case UPCOMING = 'upcoming';
    case PAST     = 'past';
}
