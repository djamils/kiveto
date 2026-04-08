<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Domain\Repository;

use App\Context\Scheduling\Domain\ValueObject\WaitingRoomEntryId;
use App\Context\Scheduling\Domain\WaitingRoomEntry;

interface WaitingRoomEntryRepositoryInterface
{
    public function save(WaitingRoomEntry $entry): void;

    public function findById(WaitingRoomEntryId $id): ?WaitingRoomEntry;
}
