<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CreateWaitingRoomWalkInEntry;

use App\Scheduling\Application\Port\AnimalExistenceCheckerInterface;
use App\Scheduling\Application\Port\OwnerExistenceCheckerInterface;
use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\WaitingRoomEntry;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateWaitingRoomWalkInEntryHandler
{
    public function __construct(
        private WaitingRoomEntryRepositoryInterface $waitingRoomEntryRepository,
        private OwnerExistenceCheckerInterface $ownerExistenceChecker,
        private AnimalExistenceCheckerInterface $animalExistenceChecker,
        private UuidGeneratorInterface $uuidGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CreateWaitingRoomWalkInEntry $command): string
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $ownerId  = $command->ownerId ? OwnerId::fromString($command->ownerId) : null;
        $animalId = $command->animalId ? AnimalId::fromString($command->animalId) : null;

        // Validate owner exists if provided
        if (null !== $ownerId && !$this->ownerExistenceChecker->exists($ownerId)) {
            throw new \InvalidArgumentException(\sprintf(
                'Owner with ID "%s" does not exist.',
                $command->ownerId
            ));
        }

        // Validate animal exists if provided
        if (null !== $animalId && !$this->animalExistenceChecker->exists($animalId)) {
            throw new \InvalidArgumentException(\sprintf(
                'Animal with ID "%s" does not exist.',
                $command->animalId
            ));
        }

        $entryId     = WaitingRoomEntryId::fromString($this->uuidGenerator->generate());
        $arrivalMode = WaitingRoomArrivalMode::from($command->arrivalMode);

        $entry = WaitingRoomEntry::createWalkIn(
            id: $entryId,
            clinicId: $clinicId,
            ownerId: $ownerId,
            animalId: $animalId,
            foundAnimalDescription: $command->foundAnimalDescription,
            arrivalMode: $arrivalMode,
            priority: $command->priority,
            triageNotes: $command->triageNotes,
            arrivedAtUtc: $this->clock->now(),
        );

        $this->waitingRoomEntryRepository->save($entry);

        return $entryId->toString();
    }
}
