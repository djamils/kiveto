<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\DiscardExpiredLot;

/**
 * « Sortir en perte » the remains of an expired lot.
 */
final readonly class DiscardExpiredLot
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $lotId,
        public ?string $performedBy = null,
    ) {
    }
}
