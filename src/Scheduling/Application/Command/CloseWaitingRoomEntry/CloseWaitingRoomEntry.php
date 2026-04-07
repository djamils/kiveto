<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Command\CloseWaitingRoomEntry;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CloseWaitingRoomEntry implements CommandInterface
{
    public function __construct(
        public string $waitingRoomEntryId,
        public ?string $closedByUserId = null,
    ) {
    }
}
