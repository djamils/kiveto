<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\GetWaitingRoomEntryDetails;

/**
 * Query: Get detailed information about a waiting room entry.
 */
final readonly class GetWaitingRoomEntryDetails
{
    public function __construct(
        public string $waitingRoomEntryId,
    ) {
    }
}
