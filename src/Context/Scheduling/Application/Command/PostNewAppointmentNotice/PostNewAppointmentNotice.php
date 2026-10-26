<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\PostNewAppointmentNotice;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Tells the appointment's veterinarian, in the bell, that someone else booked
 * it (« Nouveau RDV »). Queued on `async` by the booking: the booking
 * transaction never reads another bounded context for it.
 */
final readonly class PostNewAppointmentNotice implements CommandInterface
{
    /**
     * @param string|null $actorUserId the member who booked it (null: booked by the system)
     */
    public function __construct(
        public string $appointmentId,
        public ?string $actorUserId,
    ) {
    }
}
