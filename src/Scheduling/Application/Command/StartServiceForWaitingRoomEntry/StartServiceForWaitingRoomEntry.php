<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\StartServiceForWaitingRoomEntry;

use App\Shared\Application\Bus\CommandInterface;

final readonly class StartServiceForWaitingRoomEntry implements CommandInterface
{
    public function __construct(
        public string $waitingRoomEntryId,
        public ?string $serviceStartedByUserId = null,
    ) {
    }
}
