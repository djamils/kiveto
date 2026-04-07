<?php

declare(strict_types=1);

namespace App\ClinicalCare\Application\Command\StartConsultationFromWaitingRoomEntry;

use App\Shared\Application\Bus\CommandInterface;

final readonly class StartConsultationFromWaitingRoomEntry implements CommandInterface
{
    public function __construct(
        public string $waitingRoomEntryId,
        public string $startedByUserId,
    ) {
    }
}
