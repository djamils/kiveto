<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ConfirmNewClientAppointment;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The clinic confirms a new client's pending request (slice 7): the client
 * file and the animal chosen or created by ClientPortal's
 * `ConfirmBookingRequest` (« Demandes Portal », or the agenda, a check-in,
 * a consultation start, a completion — S7-01) are set on it, then it is
 * confirmed; `silent` when the client is on site.
 */
final readonly class ConfirmNewClientAppointment implements CommandInterface
{
    public function __construct(
        public string $appointmentId,
        public string $clinicId,
        public string $ownerId,
        public string $animalId,
        public bool $silent = false,
    ) {
    }
}
