<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Flux;

/**
 * A line of the « passages terminés » fold of the Planifié column: a patient
 * seen and gone today (when it left, what it paid, its report) or a booking
 * nobody came to (to reschedule).
 */
final readonly class FluxPassage
{
    public function __construct(
        public ?string $appointmentTime,
        public ?string $departureTime,
        public string $emoji,
        public string $name,
        public string $detail,
        public ?string $amountLabel,
        public ?string $consultationId,
        public bool $isNoShow,
    ) {
    }

    /** Booking time first — a walk-in takes its place by the time it left. */
    public function sortKey(): string
    {
        return $this->appointmentTime ?? $this->departureTime ?? '';
    }
}
