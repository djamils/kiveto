<?php

declare(strict_types=1);

namespace App\Fixtures\Scheduling;

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

final class AppointmentFactory
{
    public function __construct(
        private readonly AppointmentRepositoryInterface $appointmentRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
    ) {
    }

    public function create(
        string $clinicId,
        ?string $ownerId = null,
        ?string $animalId = null,
        ?string $practitionerUserId = null,
        \DateTimeImmutable $startsAtUtc = null,
        int $durationMinutes = 30,
        ?string $reason = null,
        ?string $notes = null,
    ): Appointment {
        $startsAtUtc = $startsAtUtc ?? new \DateTimeImmutable('+1 day 09:00:00');

        $practitionerAssignee = null;
        if (null !== $practitionerUserId) {
            $practitionerAssignee = new PractitionerAssignee(UserId::fromString($practitionerUserId));
        }

        $appointment = Appointment::schedule(
            id: AppointmentId::fromString($this->uuidGenerator->generate()),
            clinicId: ClinicId::fromString($clinicId),
            ownerId: $ownerId ? OwnerId::fromString($ownerId) : null,
            animalId: $animalId ? AnimalId::fromString($animalId) : null,
            practitionerAssignee: $practitionerAssignee,
            timeSlot: new TimeSlot($startsAtUtc, $durationMinutes),
            reason: $reason,
            notes: $notes,
            createdAt: new \DateTimeImmutable(),
        );

        $this->appointmentRepository->save($appointment);

        return $appointment;
    }
}
