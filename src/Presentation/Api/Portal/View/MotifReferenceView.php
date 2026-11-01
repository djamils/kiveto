<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

/** The motif of an appointment: `id` null and label « Rendez-vous » when taken without online motif. */
final readonly class MotifReferenceView
{
    public function __construct(
        public ?string $id,
        public string $label,
    ) {
    }
}
