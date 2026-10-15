<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Flux;

/**
 * How an unidentified emergency came in, as the « Identifier le patient »
 * window recalls it: when, brought by whom (the owner, a third party, the
 * emergency services), the declarant to call back and a chip read at the desk.
 */
final readonly class FluxIntake
{
    /**
     * @param 'proprio'|'tiers'|'secours' $broughtBy
     */
    public function __construct(
        public string $time,
        public string $broughtBy,
        public ?string $declarant,
        public ?string $declarantPhone,
        public ?string $scannedMicrochip,
        public ?string $species,
    ) {
    }
}
