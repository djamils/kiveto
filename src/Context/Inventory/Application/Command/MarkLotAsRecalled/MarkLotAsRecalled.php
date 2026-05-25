<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\MarkLotAsRecalled;

final readonly class MarkLotAsRecalled
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $lotId,
        public string $reason,
        public ?string $occurredAt = null,
    ) {
    }
}
