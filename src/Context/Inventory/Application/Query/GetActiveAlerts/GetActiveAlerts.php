<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Query\GetActiveAlerts;

final readonly class GetActiveAlerts
{
    public function __construct(
        public string $clinicId,
        public ?string $severity = null,
    ) {
    }
}
