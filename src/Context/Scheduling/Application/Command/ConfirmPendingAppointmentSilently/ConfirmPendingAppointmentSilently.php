<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ConfirmPendingAppointmentSilently;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The client is on site (a consultation starts from the appointment): a
 * pending request becomes PLANNED without any message. No-op on any other
 * status.
 */
final readonly class ConfirmPendingAppointmentSilently implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
        // Null when the caller already scoped the appointment to its clinic
        public ?string $clinicId = null,
    ) {
    }
}
