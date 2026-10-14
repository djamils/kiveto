<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Flux;

/** A patient who left the clinic today, as the « partis aujourd'hui » bar lists it. */
final readonly class FluxDeparture
{
    public function __construct(
        public string $name,
        public string $time,
    ) {
    }
}
