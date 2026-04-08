<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Command\ScheduleAppointment;

use App\Context\Scheduling\Application\Port\AnimalExistenceCheckerInterface;
use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\OwnerExistenceCheckerInterface;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AnimalId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\OwnerId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
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
        private OwnerExistenceCheckerInterface $ownerExistenceChecker,
        private AnimalExistenceCheckerInterface $animalExistenceChecker,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ScheduleAppointment $command): string
    {
        // Capture "now" once so eligibility check and createdAt are consistent.
        $now = $this->clock->now();

        $clinicId = ClinicId::fromString($command->clinicId);
        $ownerId  = $command->ownerId ? OwnerId::fromString($command->ownerId) : null;
        $animalId = $command->animalId ? AnimalId::fromString($command->animalId) : null;

        // Validate owner exists if provided
        if (null !== $ownerId && !$this->ownerExistenceChecker->exists($ownerId)) {
            throw new \InvalidArgumentException(\sprintf('Owner with ID "%s" does not exist.', $command->ownerId));
        }

        // Validate animal exists if provided
        if (null !== $animalId && !$this->animalExistenceChecker->exists($animalId)) {
            throw new \InvalidArgumentException(\sprintf('Animal with ID "%s" does not exist.', $command->animalId));
        }

        $practitionerAssignee = null;
        if (null !== $command->practitionerUserId) {
            $practitionerUserId = UserId::fromString($command->practitionerUserId);

            // Validate practitioner is eligible
            $isEligible = $this->membershipEligibilityChecker->isUserEligibleForClinicAt(
                userId: $practitionerUserId,
                clinicId: $clinicId,
                at: $now,
                allowedRoles: ['VETERINARY', 'ASSISTANT_VETERINARY'],
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

            $practitionerAssignee = new PractitionerAssignee($practitionerUserId);
        } else {
            $timeSlot = new TimeSlot($command->startsAtUtc, $command->durationMinutes);
        }

        $appointmentId = AppointmentId::fromString($this->uuidGenerator->generate());

        $appointment = Appointment::schedule(
            id: $appointmentId,
            clinicId: $clinicId,
            ownerId: $ownerId,
            animalId: $animalId,
            practitionerAssignee: $practitionerAssignee,
            timeSlot: $timeSlot,
            reason: $command->reason,
            notes: $command->notes,
            createdAt: $now,
        );

        $this->appointmentRepository->save($appointment);

        return $appointmentId->toString();
    }
}
