<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\UpdateWaitingRoomTriage;

use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\WaitingRoomArrivalMode;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateWaitingRoomTriageHandler
{
    public function __construct(
        private WaitingRoomEntryRepositoryInterface $waitingRoomEntryRepository,
    ) {
    }

    public function __invoke(UpdateWaitingRoomTriage $command): void
    {
        $entryId = WaitingRoomEntryId::fromString($command->waitingRoomEntryId);

        $entry = $this->waitingRoomEntryRepository->findById($entryId);
        if (null === $entry) {
            throw new \InvalidArgumentException(\sprintf(
                'Waiting room entry with ID "%s" does not exist.',
                $command->waitingRoomEntryId
            ));
        }

        $arrivalMode = WaitingRoomArrivalMode::from($command->arrivalMode);

        $entry->updateTriage(
            priority: $command->priority,
            triageNotes: $command->triageNotes,
            arrivalMode: $arrivalMode,
        );

        $this->waitingRoomEntryRepository->save($entry);
    }
}
