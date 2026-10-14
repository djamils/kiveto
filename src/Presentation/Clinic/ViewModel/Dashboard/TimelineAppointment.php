<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * An appointment of the day as the timeline card shows it.
 *
 * `state`: `planned` (still to come or late), `onsite` (checked in, the
 * patient is in the clinic), `done` (completed) or `noshow`.
 */
final readonly class TimelineAppointment
{
    /**
     * @param 'planned'|'onsite'|'done'|'noshow' $state
     */
    public function __construct(
        public string $id,
        public string $emoji,
        public string $title,
        public string $subtitle,
        public string $durationLabel,
        public string $state,
        public ?string $animalId,
        public ?string $animalName,
    ) {
    }
}
