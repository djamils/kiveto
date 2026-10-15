<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * A row of the « Fil du jour »: an appointment of the day, or an emergency
 * that walked in without one (`isWalkIn`, placed at its arrival time).
 *
 * `state`: `planned` (still to come or late), `onsite` (the patient is in
 * the clinic), `done` (seen) or `noshow`. `kind` colours the card: `consult`,
 * `chir` (surgery) or `urg` (emergency).
 */
final readonly class TimelineAppointment
{
    /**
     * @param 'planned'|'onsite'|'done'|'noshow' $state
     * @param 'consult'|'chir'|'urg'             $kind
     */
    public function __construct(
        public string $id,
        public string $emoji,
        public string $title,
        public string $subtitle,
        public ?string $durationLabel,
        public string $state,
        public ?string $animalId,
        public ?string $animalName,
        public string $kind = 'consult',
        public ?string $tag = null,
        public string $time = '',
        public int $lateMinutes = 0,
        public bool $isWalkIn = false,
        public ?string $admissionId = null,
        public ?string $location = null,
        public string $breed = '',
        public string $motif = '',
        public ?string $consultationId = null,
        public ?PatientFacts $facts = null,
    ) {
    }

    public function isDone(): bool
    {
        return \in_array($this->state, ['done', 'noshow'], true);
    }
}
