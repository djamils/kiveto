<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\RequestAppointmentReminder;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Sends the SMS reminder of one appointment if it is still due (queued on
 * `async` by the 15-minute scan).
 */
final readonly class RequestAppointmentReminder implements CommandInterface
{
    public function __construct(public string $appointmentId)
    {
    }
}
