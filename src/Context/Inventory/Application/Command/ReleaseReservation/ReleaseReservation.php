<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ReleaseReservation;

final readonly class ReleaseReservation
{
    public function __construct(
        public string $stockItemId,
        public string $clinicId,
        public string $quantityAmount,
        public string $quantityUnit,
    ) {
    }
}
