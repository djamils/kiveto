<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\DeclineExpiredPendingAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Declines one pending request the clinic did not answer in time (reason
 * `expired`). Queued on `async` by the 15-minute scan.
 */
final readonly class DeclineExpiredPendingAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
    ) {
    }
}
