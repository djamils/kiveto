<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\ScheduleAppointment;

use App\Scheduling\Application\Port\AnimalExistenceCheckerInterface;
use App\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Scheduling\Application\Port\OwnerExistenceCheckerInterface;
use App\Scheduling\Domain\Appointment;
use App\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Scheduling\Domain\ValueObject\UserId;
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
            if (!$this->membershipEligibilityChecker->isUserEligibleForClinicAt(
                userId: $practitionerUserId,
                clinicId: $clinicId,
                at: $this->clock->now(),
                allowedRoles: ['VETERINARY', 'ASSISTANT_VETERINARY'],
            )) {
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
            createdAt: $this->clock->now(),
        );

        $this->appointmentRepository->save($appointment);

        return $appointmentId->toString();
    }
}
