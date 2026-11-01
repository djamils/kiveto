<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\EventSubscriber;

use App\Context\Scheduling\Application\Port\BookedPetArchiverInterface;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * S4g, D1: the animal registered on the Portal for a booking that was
 * declined or expired is archived, unless it has another appointment (not
 * withdrawn) or a clinical record. Runs in the transaction of the decline.
 */
final readonly class ArchivePetCreatedForDeclinedBooking
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private BookedPetArchiverInterface $archiver,
    ) {
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function __invoke(AppointmentDeclined $event): void
    {
        $appointmentId = AppointmentId::fromString($event->appointmentId());
        $appointment   = $this->appointments->findById($appointmentId);
        $petId         = $appointment?->createdPetId();

        if (null === $appointment || null === $petId) {
            return;
        }

        $clinicId = $appointment->clinicId();

        if ($this->appointments->hasOtherAppointmentForAnimal($clinicId, $petId, $appointmentId)) {
            return;
        }

        $this->archiver->archiveUnlessRecorded($clinicId->toString(), $petId);
    }
}
