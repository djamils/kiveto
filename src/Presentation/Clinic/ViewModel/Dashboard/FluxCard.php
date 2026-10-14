<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Dashboard;

/**
 * A patient on site, as the « Flux ambulatoire » column shows it.
 */
final readonly class FluxCard
{
    /**
     * @param list<string> $steps the patient's own sequence of steps
     */
    public function __construct(
        public string $admissionId,
        public string $emoji,
        public string $name,
        public string $subtitle,
        public bool $isEmergency,
        public string $chrono,
        public bool $isLong,
        public array $steps,
        public int $currentStep,
        public string $where,
        public string $who,
    ) {
    }
}
