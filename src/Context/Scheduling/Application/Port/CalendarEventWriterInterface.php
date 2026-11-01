<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

/**
 * Writes the calendar file (.ics) of an appointment downloaded from the
 * Portal (`METHOD:PUBLISH`).
 */
interface CalendarEventWriterInterface
{
    public function publish(CalendarEvent $event): string;
}
