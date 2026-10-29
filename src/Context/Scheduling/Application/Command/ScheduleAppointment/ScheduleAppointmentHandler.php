<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ScheduleAppointment;

use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockFinderInterface;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
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
        private PlanningBlockFinderInterface $planningBlockFinder,
        private ClinicTimezoneResolverInterface $timezoneResolver,
        private DomainEventPublisher $domainEventPublisher,
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
            throw new \DomainException(\sprintf(
                'User "%s" is not eligible as practitioner for clinic "%s".',
                $command->practitionerUserId,
                $command->clinicId
            ));
        }

        $timeSlot = new TimeSlot($command->startsAtUtc, $command->durationMinutes);

        // Check for overlaps
        if ($this->conflictChecker->hasOverlap($clinicId, $practitionerUserId, $timeSlot, null)) {
            throw new \DomainException(\sprintf(
                'Practitioner "%s" has an overlapping appointment at %s.',
                $command->practitionerUserId,
                $command->startsAtUtc->format('Y-m-d H:i')
            ));
        }

        // Check planning block constraints (SOFT: no block = allowed; HARD: type/capacity)
        $clinicTz    = $this->timezoneResolver->resolveTimezone($clinicId);
        $startsLocal = $timeSlot->startsAtUtc()->setTimezone($clinicTz);
        $endsLocal   = $timeSlot->endsAtUtc()->setTimezone($clinicTz);

        $block = $this->planningBlockFinder->findActiveBlockFor(
            $clinicId,
            $practitionerUserId,
            $startsLocal->format('Y-m-d'),
            $startsLocal->format('H:i'),
            $endsLocal->format('H:i'),
        );

        if (null !== $block) {
            if (!$block->acceptsAppointments) {
                throw new \DomainException(
                    'Cannot schedule appointment in a ' . $block->type->value . ' block.'
                );
            }

            // The count covers the whole block occurrence, so does the capacity:
            // « 4 per hour » on a 08:00–12:00 block holds 16 bookings.
            $windowMinutes  = max($block->durationMinutes, $timeSlot->durationMinutes());
            $capacityOfSpan = (int) floor($block->capacityPerHour * $windowMinutes / 60);
            if ($capacityOfSpan > 0 && $block->currentAppointmentCount >= $capacityOfSpan) {
                throw new \DomainException('Planning block capacity reached.');
            }
        }

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
