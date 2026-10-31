<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class PrescriptionContentLine
{
    public function __construct(
        public string $label,
        public string $quantity,
        public string $posology,
    ) {
    }
}
