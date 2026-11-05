<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListLastClosedConsultations;

/** An animal's latest closed consultation. */
final readonly class LastClosedConsultation
{
    /**
     * @param list<string> $motifs in their order
     */
    public function __construct(
        public string $consultationId,
        public string $animalId,
        public string $practitionerUserId,
        public string $startedAtUtc,
        public ?string $chiefComplaint,
        public array $motifs,
    ) {
    }
}
