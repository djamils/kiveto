<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\GetWaitingRoomEntryDetails;

use App\Scheduling\Application\Port\WaitingRoomReadRepositoryInterface;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetWaitingRoomEntryDetailsHandler
{
    public function __construct(
        private WaitingRoomReadRepositoryInterface $waitingRoomReadRepository,
    ) {
    }

    public function __invoke(GetWaitingRoomEntryDetails $query): WaitingRoomEntryDetailsDTO
    {
        $waitingRoomEntryId = WaitingRoomEntryId::fromString($query->waitingRoomEntryId);

        return $this->waitingRoomReadRepository->findById($waitingRoomEntryId);
    }
}
