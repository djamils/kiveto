<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\RescheduleOnlineAppointment;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Application\Service\OnlineCancellationWindow;
use App\Context\Scheduling\Application\Service\OnlineChangeNotice;
use App\Context\Scheduling\Application\Service\OnlineSlotChecker;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Exception\AppointmentNotModifiableException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * S4f: only a portal booking that carries a motif moves online, within the
 * cancellation window, to a slot offered for that motif (HARD rule), with a
 * practitioner of the motif. Both lock sets are taken (P1) and the moved
 * appointment is left out of overlap and capacity. The status is kept; a
 * pending one gets the manual lead time and a new expiry. The same slot
 * with the same practitioner changes nothing.
 *
 * Refusals: `OnlineBookingNotFoundException` (appointment unknown or of
 * another clinic, practitioner not bookable for the motif),
 * `AppointmentNotModifiableException` (terminal status),
 * `BookingNotOpenException` (not a portal booking with a motif, settings
 * disabled, clinic not active, motif archived or no longer online),
 * `CancellationWindowClosedException`, `SlotTakenException`.
 */
#[AsMessageHandler]
final readonly class RescheduleOnlineAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private OnlineBookingSettingsRepositoryInterface $settings,
        private OnlineSlotChecker $slots,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private PractitionerDayLockInterface $dayLock,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
        private OnlineChangeNotice $notice,
    ) {
    }

    public function __invoke(RescheduleOnlineAppointment $command): string
    {
        $clinicId    = ClinicId::fromString($command->clinicId);
        $appointment = $this->load($clinicId, $command->appointmentId);
        $motifId     = $appointment->bookingMotifId();

        if (AppointmentSource::PORTAL !== $appointment->source() || null === $motifId) {
            throw new BookingNotOpenException($command->clinicId);
        }

        if (!Uuid::isValid($command->practitionerUserId)) {
            throw OnlineBookingNotFoundException::practitioner($command->practitionerUserId);
        }

        $practitionerId = UserId::fromString($command->practitionerUserId);
        $startsAtUtc    = $command->startsAt->setTimezone(new \DateTimeZone('UTC'));

        if ($appointment->timeSlot()->startsAtUtc()->getTimestamp() === $startsAtUtc->getTimestamp() && $appointment->practitionerAssignee()->userId()->equals($practitionerId)) {
            return $command->appointmentId;
        }

        $settings = $this->settings->forClinic($clinicId);
        OnlineCancellationWindow::assertOpen($appointment, $settings, $this->clock->now());

        $motif       = $this->motif($clinicId, BookingMotifId::fromString($motifId), $practitionerId);
        $newTimeSlot = new TimeSlot($startsAtUtc, $motif->durationMin());
        $oldSlot     = $appointment->timeSlot();
        $oldVet      = $appointment->practitionerAssignee()->userId();
        $clinicTz    = $this->timezoneResolver->resolveTimezone($clinicId);

        // The union of the days left and of the days taken (P1)
        $this->dayLock->lock($clinicId, LockKeys::merge(
            LockKeys::forSlot($oldVet, $oldSlot->startsAtUtc(), $oldSlot->endsAtUtc(), $clinicTz),
            LockKeys::forSlot($practitionerId, $newTimeSlot->startsAtUtc(), $newTimeSlot->endsAtUtc(), $clinicTz),
        ));

        // Current read: a decline or cancel committed meanwhile is seen
        $appointment = $this->appointments->findByIdForUpdate($appointment->id()) ?? throw OnlineBookingNotFoundException::appointment($command->appointmentId);
        self::assertModifiable($appointment);

        // A pending request keeps waiting for the clinic: the manual lead time applies (S4f)
        $pendingLeadTime = $appointment->status()->isPending() || null === $appointment->ownerId();

        if (!$this->slots->isBookable($clinicId, $motif->id(), $practitionerId, $startsAtUtc, $pendingLeadTime, $appointment->id())) {
            throw SlotTakenException::unavailable($command->startsAt);
        }

        $appointment->reschedule(
            $newTimeSlot,
            $appointment->status()->isPending() ? $settings->pendingExpiry($appointment->createdAt(), $startsAtUtc) : null,
        );
        $appointment->changePractitionerAssignee(new PractitionerAssignee($practitionerId));

        $this->appointments->save($appointment);

        $this->domainEventPublisher->publish($appointment);

        $this->notice->moved($appointment, $oldVet->toString());

        return $command->appointmentId;
    }

    private function load(ClinicId $clinicId, string $appointmentId): Appointment
    {
        $appointment = Uuid::isValid($appointmentId) ? $this->appointments->findById(AppointmentId::fromString($appointmentId)) : null;

        // An appointment of another clinic is reported as missing, never exposed
        if (null === $appointment || !$appointment->clinicId()->equals($clinicId)) {
            throw OnlineBookingNotFoundException::appointment($appointmentId);
        }

        self::assertModifiable($appointment);

        return $appointment;
    }

    private static function assertModifiable(Appointment $appointment): void
    {
        if ($appointment->status()->isTerminal()) {
            throw new AppointmentNotModifiableException();
        }
    }

    /**
     * A practitioner not bookable for the motif is not found (AC 49); a
     * motif archived or no longer online closes online moves.
     */
    private function motif(ClinicId $clinicId, BookingMotifId $motifId, UserId $practitionerId): BookingMotif
    {
        try {
            return $this->slots->bookableMotif($clinicId, $motifId, $practitionerId);
        } catch (OnlineBookingNotFoundException $e) {
            if (OnlineBookingNotFoundException::PRACTITIONER === $e->subject) {
                throw $e;
            }

            throw new BookingNotOpenException($clinicId->toString());
        }
    }
}
