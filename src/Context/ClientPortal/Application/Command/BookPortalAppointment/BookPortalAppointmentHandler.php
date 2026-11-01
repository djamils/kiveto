<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\BookPortalAppointment;

use App\Context\ClientPortal\Application\Port\OnlineBookingInterface;
use App\Context\ClientPortal\Application\Service\PortalBookingGuard;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The appointment is for the pet's primary owner (S4b-F4). Refusals:
 * `PetNotFoundException`, `PetNotBookableException`, then Scheduling's.
 */
#[AsMessageHandler]
final readonly class BookPortalAppointmentHandler
{
    public function __construct(
        private PortalBookingGuard $guard,
        private OnlineBookingInterface $booking,
    ) {
    }

    public function __invoke(BookPortalAppointment $command): string
    {
        $pet = $this->guard->bookablePet($command->userId, $command->clinicId, $command->petId);

        return $this->booking->book(
            $command->clinicId,
            $command->motifId,
            $command->practitionerId,
            $command->startsAt,
            $pet->primaryOwnerClientId,
            $pet->id,
        );
    }
}
