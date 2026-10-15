<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationRegister;

/**
 * One row of the register as the page draws it. Patient and practitioner stay
 * raw identifiers the caller resolves to names.
 */
final readonly class ConsultationRegisterItem
{
    /**
     * @param list<string>                              $motifs
     * @param list<string>                              $labAnalyses labels of the laboratory acts billed
     * @param list<array{code: ?string, label: string}> $billedItems
     */
    public function __construct(
        public string $consultationId,
        public string $admissionId,
        public string $patientId,
        public string $practitionerUserId,
        public string $status,
        public string $stage,
        public string $startedAtUtc,
        public ?string $closedAtUtc,
        public ?int $durationMinutes,
        public ?string $chiefComplaint,
        public ?string $summary,
        public array $motifs,
        public ?string $primaryDiagnosis,
        public ?string $weightKg,
        public bool $isUrgent,
        public bool $hasSurgery,
        public array $labAnalyses,
        public array $billedItems,
        public int $totalHtMinorUnits,
        public int $totalTvaMinorUnits,
        public int $totalTtcMinorUnits,
        public string $currency,
    ) {
    }

    /**
     * What the visit was about, in the words the register shows first.
     */
    public function motif(): string
    {
        return $this->motifs[0] ?? $this->chiefComplaint ?? 'Consultation';
    }
}
