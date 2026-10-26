<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\SyncAppointmentNotification;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Tells the client what changed about an appointment, if anything (D2).
 * Queued on `async` 2 minutes after each appointment event: what happened in
 * between is told once, as it ended up.
 */
final readonly class SyncAppointmentNotification implements CommandInterface
{
    public function __construct(public string $appointmentId)
    {
    }
}
