<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetExpiringLots;

final readonly class GetExpiringLots
{
    public function __construct(
        public string $clinicId,
        public int $withinDays = 30,
    ) {
    }
}
