<?php

declare(strict_types=1);

namespace App\Fixtures\Scheduling;

use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\WaitingRoomEntry;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;

final class WaitingRoomEntryFactory
{
    public function __construct(
        private readonly WaitingRoomEntryRepositoryInterface $waitingRoomEntryRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
    ) {
    }

    public function createFromAppointment(
        string $clinicId,
        string $appointmentId,
        ?string $ownerId = null,
        ?string $animalId = null,
        WaitingRoomArrivalMode $arrivalMode = WaitingRoomArrivalMode::STANDARD,
        int $priority = 0,
    ): WaitingRoomEntry {
        $entry = WaitingRoomEntry::createFromAppointment(
            id: WaitingRoomEntryId::fromString($this->uuidGenerator->generate()),
            clinicId: ClinicId::fromString($clinicId),
            linkedAppointmentId: AppointmentId::fromString($appointmentId),
            ownerId: $ownerId ? OwnerId::fromString($ownerId) : null,
            animalId: $animalId ? AnimalId::fromString($animalId) : null,
            arrivalMode: $arrivalMode,
            priority: $priority,
            arrivedAtUtc: new \DateTimeImmutable(),
        );

        $this->waitingRoomEntryRepository->save($entry);

        return $entry;
    }

    public function createWalkIn(
        string $clinicId,
        ?string $ownerId = null,
        ?string $animalId = null,
        ?string $foundAnimalDescription = null,
        WaitingRoomArrivalMode $arrivalMode = WaitingRoomArrivalMode::STANDARD,
        int $priority = 0,
        ?string $triageNotes = null,
    ): WaitingRoomEntry {
        $entry = WaitingRoomEntry::createWalkIn(
            id: WaitingRoomEntryId::fromString($this->uuidGenerator->generate()),
            clinicId: ClinicId::fromString($clinicId),
            ownerId: $ownerId ? OwnerId::fromString($ownerId) : null,
            animalId: $animalId ? AnimalId::fromString($animalId) : null,
            foundAnimalDescription: $foundAnimalDescription,
            arrivalMode: $arrivalMode,
            priority: $priority,
            triageNotes: $triageNotes,
            arrivedAtUtc: new \DateTimeImmutable(),
        );

        $this->waitingRoomEntryRepository->save($entry);

        return $entry;
    }
}
