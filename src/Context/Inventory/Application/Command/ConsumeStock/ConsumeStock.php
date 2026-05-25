<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ConsumeStock;

final readonly class ConsumeStock
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $quantityAmount,
        public string $quantityUnit,
        public ?string $occurredAt,
        public ?string $reference,
        public ?string $performedBy,
        public ?string $note,
        public string $reason = 'CONSUMPTION_CONSULTATION',
    ) {
    }
}
