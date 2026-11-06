<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\RescheduleAppointment;

use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Application\Service\AppointmentParties;
use App\Context\Scheduling\Application\Service\PlanningBlockGate;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Exception\PractitionerNotBookableException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\PractitionerDay;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RescheduleAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private AppointmentConflictCheckerInterface $conflictChecker,
        private MembershipEligibilityCheckerInterface $membershipEligibilityChecker,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
        private OnlineBookingSettingsRepositoryInterface $onlineBookingSettings,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private PractitionerDayLockInterface $dayLock,
        private PlanningBlockGate $planningBlockGate,
        private AppointmentParties $parties,
    ) {
    }

    public function __invoke(RescheduleAppointment $command): void
    {
        $appointmentId = AppointmentId::fromString($command->appointmentId);
        $clinicId      = ClinicId::fromString($command->clinicId);

        $appointment = $this->appointmentRepository->findById($appointmentId);
        if (null === $appointment) {
            throw new \DomainException('Rendez-vous introuvable.');
        }

        if (!$appointment->clinicId()->equals($clinicId)) {
            throw new \DomainException('Rendez-vous introuvable.');
        }

        $practitionerUserId = UserId::fromString($command->practitionerUserId);

        // Validate practitioner eligibility
        $isEligible = $this->membershipEligibilityChecker->isUserEligibleForClinicAt(
            userId: $practitionerUserId,
            clinicId: $clinicId,
            at: $this->clock->now(),
            allowedRoles: ['VETERINARY', 'VETERINARY_ASSISTANT'],
        );
        if (!$isEligible) {
            throw PractitionerNotBookableException::at($command->practitionerUserId, $command->clinicId);
        }

        $newTimeSlot = new TimeSlot($command->startsAtUtc, $command->durationMinutes);
        $clinicTz    = $this->timezoneResolver->resolveTimezone($clinicId);
        $newDays     = LockKeys::forSlot($practitionerUserId, $newTimeSlot->startsAtUtc(), $newTimeSlot->endsAtUtc(), $clinicTz);

        // The union of the days left and of the days taken (P1)
        $this->dayLock->lock($clinicId, LockKeys::merge($this->daysOf($appointment, $clinicTz), $newDays));

        // Current read: a move, cancel or decline committed meanwhile is seen
        $locked = $this->appointmentRepository->findByIdForUpdate($appointmentId) ?? throw new \DomainException('Rendez-vous introuvable.');

        // Moved meanwhile: the days it occupies now are locked too
        if (!$locked->timeSlot()->equals($appointment->timeSlot()) || !$locked->practitionerAssignee()->equals($appointment->practitionerAssignee())) {
            $this->dayLock->lock($clinicId, LockKeys::merge($this->daysOf($locked, $clinicTz), $newDays));
        }

        $appointment = $locked;

        // Cancelled or declined meanwhile: refused before any check of the new slot
        if ($appointment->status()->isTerminal()) {
            throw new \DomainException('Cannot reschedule a terminated appointment.');
        }

        // Check for overlaps (exclude self), as a current read
        if ($this->conflictChecker->hasOverlapLocked($clinicId, $practitionerUserId, $newTimeSlot, $appointmentId)) {
            throw SlotTakenException::overlap($command->practitionerUserId, $command->startsAtUtc);
        }

        // The same SOFT block rule as a new booking (AC 148); the moved booking does not count
        $this->planningBlockGate->assertAdmits($clinicId, $practitionerUserId, $newTimeSlot, $clinicTz, $appointmentId);

        $newAssignee = new PractitionerAssignee($practitionerUserId);

        // A pending request moved elsewhere expires by its new start (AC 142)
        $pendingExpiresAt = $appointment->status()->isPending()
            ? $this->onlineBookingSettings->forClinic($clinicId)->pendingExpiry($appointment->createdAt(), $newTimeSlot->startsAtUtc())
            : null;

        // Refused when the new expiry is already past: the request would expire at once
        $appointment->reschedule($newTimeSlot, $pendingExpiresAt, $this->clock->now());
        $appointment->changePractitionerAssignee($newAssignee);
        $this->parties->assertOfClinic($command->clinicId, $command->ownerId, $command->animalId);
        $appointment->changeOwnerAndAnimal($command->ownerId, $command->animalId);

        if ($command->changeDetails) {
            $appointment->changeDetails($command->reason, $command->notes);
        }

        $this->appointmentRepository->save($appointment);

        $this->domainEventPublisher->publish($appointment);
    }

    /**
     * @return list<PractitionerDay>
     */
    private function daysOf(Appointment $appointment, \DateTimeZone $clinicTz): array
    {
        $slot = $appointment->timeSlot();

        return LockKeys::forSlot($appointment->practitionerAssignee()->userId(), $slot->startsAtUtc(), $slot->endsAtUtc(), $clinicTz);
    }
}
