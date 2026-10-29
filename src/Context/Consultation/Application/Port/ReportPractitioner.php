<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Port;

final readonly class ReportPractitioner
{
    public function __construct(
        public ?string $displayName,
        /** Stored as a code: "DR", "PR". */
        public ?string $professionalTitle,
    ) {
    }
}
