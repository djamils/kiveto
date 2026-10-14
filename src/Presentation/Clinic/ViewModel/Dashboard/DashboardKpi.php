<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * One figure of the « La journée en chiffres » strip.
 */
final readonly class DashboardKpi
{
    /**
     * @param ''|'up'|'warn' $noteTone
     */
    public function __construct(
        public string $value,
        public string $label,
        public ?string $note = null,
        public string $noteTone = '',
        public ?string $id = null,
    ) {
    }
}
