<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\SearchConsultations;

/**
 * One row of the consultation list.
 *
 * Carries only what this context owns: the patient and practitioner are raw
 * identifiers the caller resolves to names.
 */
final readonly class ConsultationListItemView
{
    /**
     * @param list<string> $motifs
     */
    public function __construct(
        public string $consultationId,
        public string $patientId,
        public string $practitionerUserId,
        public string $status,
        public string $startedAtUtc,
        public ?string $closedAtUtc,
        public ?string $chiefComplaint,
        public array $motifs,
        public int $totalHtMinorUnits,
        public int $totalTvaMinorUnits,
        public int $totalTtcMinorUnits,
        public string $currency,
    ) {
    }
}
