<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Flux;

/** The appointments of the « Planifié » column booked at the same time. */
final readonly class FluxSlot
{
    /**
     * @param list<FluxPatient> $patients
     */
    public function __construct(
        public string $time,
        public array $patients,
    ) {
    }
}
