<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\RescheduleAppointment;

use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
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
            throw new \DomainException(\sprintf(
                'User "%s" is not eligible as practitioner for clinic "%s".',
                $command->practitionerUserId,
                $command->clinicId,
            ));
        }

        $newTimeSlot = new TimeSlot($command->startsAtUtc, $command->durationMinutes);

        // Check for overlaps (exclude self)
        if ($this->conflictChecker->hasOverlap($clinicId, $practitionerUserId, $newTimeSlot, $appointmentId)) {
            throw new \DomainException(\sprintf(
                'Practitioner "%s" has an overlapping appointment at %s.',
                $command->practitionerUserId,
                $command->startsAtUtc->format('Y-m-d H:i'),
            ));
        }

        $newAssignee = new PractitionerAssignee($practitionerUserId);

        $appointment->reschedule($newTimeSlot);
        $appointment->changePractitionerAssignee($newAssignee);

        $this->appointmentRepository->save($appointment);
    }
}
