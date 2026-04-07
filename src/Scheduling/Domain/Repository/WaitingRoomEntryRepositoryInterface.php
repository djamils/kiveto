<?php

declare(strict_types=1);

namespace App\Scheduling\Domain\Repository;

use App\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Scheduling\Domain\WaitingRoomEntry;

interface WaitingRoomEntryRepositoryInterface
{
    public function save(WaitingRoomEntry $entry): void;

    public function findById(WaitingRoomEntryId $id): ?WaitingRoomEntry;
}
