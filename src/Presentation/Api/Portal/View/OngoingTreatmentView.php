<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

/** `ongoingTreatment` of a submitted questionnaire. */
final readonly class OngoingTreatmentView
{
    public function __construct(
        public bool $has,
        public ?string $details,
    ) {
    }
}
