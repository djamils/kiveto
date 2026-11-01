<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\BookOnlineAppointment;

use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Application\Service\OnlineSlotChecker;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Booking\BookingMotif;
use App\Context\Scheduling\Domain\Booking\BookingMotifId;
use App\Context\Scheduling\Domain\Booking\BookingMotifRepositoryInterface;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\ConfirmationMode;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
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
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Task 26: the motif is checked, the practitioner-day lock taken, then the
 * start is recomputed by the availability calculator for that single
 * candidate (current reads). Confirmed at once for an `auto` motif, else
 * pending until the clinic answers.
 *
 * Natural idempotency (S4b-F6): a booking of the same owner, animal, motif
 * and start made in the last 24 h that still holds its slot is answered
 * instead of `slot_taken`, so a retry after a lost response never books
 * twice.
 *
 * New-client mode (slice 7): no owner nor animal, always pending, with the
 * manual lead time; no natural idempotency (the Portal's Idempotency-Key
 * answers a retry). The clinic must take new clients online
 * (`newClientBookingEnabled`) on a motif that allows them, else
 * `BookingNotOpenException`.
 *
 * Refusals: `BookingNotOpenException` (settings disabled, clinic not
 * active, motif not online, practitioner not bookable for it, new clients
 * not taken),
 * `OnlineBookingNotFoundException` (unknown or archived motif),
 * `SlotTakenException` (off the grid, outside the slots offered, taken).
 */
#[AsMessageHandler]
final readonly class BookOnlineAppointmentHandler
{
    /** How long a booking answers a retry instead of `slot_taken`. */
    public const string RETRY_WINDOW = 'PT24H';

    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private BookingMotifRepositoryInterface $motifs,
        private OnlineBookingSettingsRepositoryInterface $settings,
        private OnlineSlotChecker $slots,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private PractitionerDayLockInterface $dayLock,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(BookOnlineAppointment $command): string
    {
        // A malformed id names nothing: same answer as an unknown one
        if (!Uuid::isValid($command->bookingMotifId)) {
            throw OnlineBookingNotFoundException::motif($command->bookingMotifId);
        }

        if (!Uuid::isValid($command->practitionerUserId)) {
            throw OnlineBookingNotFoundException::practitioner($command->practitionerUserId);
        }

        $now            = $this->clock->now();
        $clinicId       = ClinicId::fromString($command->clinicId);
        $motifId        = BookingMotifId::fromString($command->bookingMotifId);
        $practitionerId = UserId::fromString($command->practitionerUserId);
        $motif          = $this->motif($clinicId, $motifId, $practitionerId);
        $settings       = $this->settings->forClinic($clinicId);

        // A new client has no owner nor animal yet; a known one has both
        if ($command->newClient !== (null === $command->ownerId || null === $command->animalId)) {
            throw new \InvalidArgumentException('A new-client booking has no owner nor animal; any other has both.');
        }

        if ($command->newClient && (!$settings->isNewClientBookingEnabled() || !$motif->newClientsAllowed())) {
            throw new BookingNotOpenException($clinicId->toString());
        }

        $timeSlot = new TimeSlot($command->startsAt->setTimezone(new \DateTimeZone('UTC')), $motif->durationMin());

        // Serialise the writers of the practitioner's day(s); the reads below are current
        $this->dayLock->lock($clinicId, LockKeys::forSlot(
            $practitionerId,
            $timeSlot->startsAtUtc(),
            $timeSlot->endsAtUtc(),
            $this->timezoneResolver->resolveTimezone($clinicId),
        ));

        $existing = null === $command->ownerId || null === $command->animalId ? null : $this->appointments->findRecentOnlineBookingId(
            $clinicId,
            $command->ownerId,
            $command->animalId,
            $command->bookingMotifId,
            $timeSlot->startsAtUtc(),
            $now->sub(new \DateInterval(self::RETRY_WINDOW)),
        );

        if (null !== $existing) {
            return $existing->toString();
        }

        if (!$this->slots->isBookable($clinicId, $motifId, $practitionerId, $timeSlot->startsAtUtc(), $command->newClient)) {
            throw SlotTakenException::unavailable($command->startsAt);
        }

        $appointmentId = AppointmentId::fromString($this->uuidGenerator->generate());
        $assignee      = new PractitionerAssignee($practitionerId);

        $appointment = $command->newClient || ConfirmationMode::MANUAL === $motif->confirmationKnownClient()
            ? Appointment::request(
                id: $appointmentId,
                clinicId: $clinicId,
                practitionerAssignee: $assignee,
                timeSlot: $timeSlot,
                reason: $motif->label(),
                notes: null,
                createdAt: $now,
                ownerId: $command->ownerId,
                animalId: $command->animalId,
                bookingMotifId: $command->bookingMotifId,
                source: AppointmentSource::PORTAL,
                pendingExpiresAt: $settings->pendingExpiry($now, $timeSlot->startsAtUtc()),
                createdPetId: $command->createdPetId,
            )
            : Appointment::schedule(
                id: $appointmentId,
                clinicId: $clinicId,
                practitionerAssignee: $assignee,
                timeSlot: $timeSlot,
                reason: $motif->label(),
                notes: null,
                createdAt: $now,
                ownerId: $command->ownerId,
                animalId: $command->animalId,
                source: AppointmentSource::PORTAL,
                bookingMotifId: $command->bookingMotifId,
                createdPetId: $command->createdPetId,
            );

        $this->appointments->save($appointment);

        $this->domainEventPublisher->publish($appointment);

        return $appointmentId->toString();
    }

    /**
     * An existing motif that is not online, or a practitioner not bookable
     * for it, closes online booking for that request (Task 26); an unknown
     * or archived motif is not found (S4c).
     */
    private function motif(ClinicId $clinicId, BookingMotifId $motifId, UserId $practitionerId): BookingMotif
    {
        try {
            return $this->slots->bookableMotif($clinicId, $motifId, $practitionerId);
        } catch (OnlineBookingNotFoundException $e) {
            $motif = $this->motifs->findInClinic($motifId, $clinicId);

            if (null === $motif || $motif->isArchived()) {
                throw $e;
            }

            throw new BookingNotOpenException($clinicId->toString());
        }
    }
}
