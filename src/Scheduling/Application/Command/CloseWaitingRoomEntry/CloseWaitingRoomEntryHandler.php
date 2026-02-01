<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CloseWaitingRoomEntry;

use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\UserId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CloseWaitingRoomEntryHandler
{
    public function __construct(
        private WaitingRoomEntryRepositoryInterface $waitingRoomEntryRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(CloseWaitingRoomEntry $command): void
    {
        $entryId = WaitingRoomEntryId::fromString($command->waitingRoomEntryId);

        $entry = $this->waitingRoomEntryRepository->findById($entryId);
        if (null === $entry) {
            throw new \InvalidArgumentException(\sprintf(
                'Waiting room entry with ID "%s" does not exist.',
                $command->waitingRoomEntryId
            ));
        }

        $closedByUserId = $command->closedByUserId
            ? UserId::fromString($command->closedByUserId)
            : null;

        $entry->close($this->clock->now(), $closedByUserId);
        $this->waitingRoomEntryRepository->save($entry);
    }
}
