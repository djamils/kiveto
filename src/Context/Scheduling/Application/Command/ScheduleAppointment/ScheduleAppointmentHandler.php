<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ScheduleAppointment;

use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Application\Service\AppointmentParties;
use App\Context\Scheduling\Application\Service\PlanningBlockGate;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Exception\PractitionerNotBookableException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ScheduleAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private MembershipEligibilityCheckerInterface $membershipEligibilityChecker,
        private AppointmentConflictCheckerInterface $conflictChecker,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
        private PlanningBlockGate $planningBlockGate,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private DomainEventPublisher $domainEventPublisher,
        private PractitionerDayLockInterface $dayLock,
        private AppointmentParties $parties,
    ) {
    }

    public function __invoke(ScheduleAppointment $command): string
    {
        // Capture "now" once so eligibility check and createdAt are consistent.
        $now = $this->clock->now();

        $clinicId           = ClinicId::fromString($command->clinicId);
        $practitionerUserId = UserId::fromString($command->practitionerUserId);

        // Validate practitioner is eligible
        $isEligible = $this->membershipEligibilityChecker->isUserEligibleForClinicAt(
            userId: $practitionerUserId,
            clinicId: $clinicId,
            at: $now,
            allowedRoles: ['VETERINARY', 'VETERINARY_ASSISTANT'],
        );
        if (!$isEligible) {
            throw PractitionerNotBookableException::at($command->practitionerUserId, $command->clinicId);
        }

        $this->parties->assertOfClinic($command->clinicId, $command->ownerId, $command->animalId);

        $timeSlot = new TimeSlot($command->startsAtUtc, $command->durationMinutes);
        $clinicTz = $this->timezoneResolver->resolveTimezone($clinicId);

        // Serialise the writers of the practitioner's day(s); the reads below are current
        $this->dayLock->lock(
            $clinicId,
            LockKeys::forSlot($practitionerUserId, $timeSlot->startsAtUtc(), $timeSlot->endsAtUtc(), $clinicTz),
        );

        if ($this->conflictChecker->hasOverlapLocked($clinicId, $practitionerUserId, $timeSlot)) {
            throw SlotTakenException::overlap($command->practitionerUserId, $command->startsAtUtc);
        }

        $this->planningBlockGate->assertAdmits($clinicId, $practitionerUserId, $timeSlot, $clinicTz);

        $practitionerAssignee = new PractitionerAssignee($practitionerUserId);

        $appointmentId = AppointmentId::fromString($this->uuidGenerator->generate());

        $appointment = Appointment::schedule(
            id: $appointmentId,
            clinicId: $clinicId,
            practitionerAssignee: $practitionerAssignee,
            timeSlot: $timeSlot,
            reason: $command->reason,
            notes: $command->notes,
            createdAt: $now,
            linkedAdmissionId: $command->linkedAdmissionId,
            ownerId: $command->ownerId,
            animalId: $command->animalId,
            actorUserId: $command->actorUserId,
        );

        $this->appointmentRepository->save($appointment);

        $this->domainEventPublisher->publish($appointment);

        return $appointmentId->toString();
    }
}
