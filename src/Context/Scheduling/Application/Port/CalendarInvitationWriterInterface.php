<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * Writes the calendar file (.ics) attached to an appointment e-mail (D4).
 */
interface CalendarInvitationWriterInterface
{
    public function write(CalendarInvitation $invitation): string;
}
