<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

final readonly class ClinicSummaryView
{
    public function __construct(
        public string $name,
        public ?string $city,
    ) {
    }
}
