<?php

declare(strict_types=1);

namespace App\Context\Inventory\Application\Command\ExpireOutdatedLots;

final readonly class ExpireOutdatedLots
{
    public function __construct(
        public ?string $clinicId = null,
    ) {
    }
}
