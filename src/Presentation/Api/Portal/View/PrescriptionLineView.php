<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

final readonly class PrescriptionLineView
{
    public function __construct(
        public string $label,
        public string $quantity,
        public string $posologySummary,
    ) {
    }
}
