<?php

declare(strict_types=1);

namespace App\Scheduling\Application\Query\ListWaitingRoom;

use App\Shared\Application\Bus\QueryInterface;

final readonly class ListWaitingRoom implements QueryInterface
{
    public function __construct(
        public string $clinicId,
    ) {
    }
}
