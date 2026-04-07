<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\StartServiceForWaitingRoomEntry;

use App\Scheduling\Domain\Repository\WaitingRoomEntryRepositoryInterface;
use App\Scheduling\Domain\ValueObject\UserId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StartServiceForWaitingRoomEntryHandler
{
    public function __construct(
        private WaitingRoomEntryRepositoryInterface $waitingRoomEntryRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(StartServiceForWaitingRoomEntry $command): void
    {
        $entryId = WaitingRoomEntryId::fromString($command->waitingRoomEntryId);

        $entry = $this->waitingRoomEntryRepository->findById($entryId);
        if (null === $entry) {
            throw new \InvalidArgumentException(\sprintf(
                'Waiting room entry with ID "%s" does not exist.',
                $command->waitingRoomEntryId
            ));
        }

        $serviceStartedByUserId = $command->serviceStartedByUserId
            ? UserId::fromString($command->serviceStartedByUserId)
            : null;

        $entry->startService($this->clock->now(), $serviceStartedByUserId);
        $this->waitingRoomEntryRepository->save($entry);
    }
}
