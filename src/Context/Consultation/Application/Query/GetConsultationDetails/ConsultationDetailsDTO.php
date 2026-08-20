<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\GetConsultationDetails;

final readonly class ConsultationDetailsDTO
{
    /**
     * @param array{weightKg: ?string, temperatureC: ?string}|null                     $vitals
     * @param list<array{noteType: string, content: string, createdAt: string}>        $notes
     * @param list<array{label: string, quantity: string, performedAt: string}>        $acts
     * @param list<array{id: string, label: string}>                                   $motifs
     * @param list<array{id: string, type: string, value: string, recordedAt: string}> $typedVitals
     * @param list<array{
     *     id: string,
     *     system: string,
     *     status: string,
     *     notes: ?string,
     *     structuredData: array<string, string>
     * }> $examSystems
     * @param list<array{
     *     id: string,
     *     code: ?string,
     *     label: string,
     *     certainty: string,
     *     note: ?string,
     *     isPrimary: bool,
     *     source: string
     * }> $diagnoses
     * @param list<array{
     *     id: string,
     *     kind: string,
     *     description: string,
     *     catalogCode: ?string,
     *     posology: ?string,
     *     durationDays: ?int,
     *     followUpDays: ?int,
     *     quantity: float,
     *     unitPriceMinorUnits: ?int,
     *     currency: ?string
     * }> $planActions
     * @param list<array{
     *     id: string,
     *     articleId: ?string,
     *     code: ?string,
     *     label: string,
     *     dose: ?string,
     *     frequency: ?string,
     *     durationDays: ?int,
     *     route: ?string,
     *     quantity: float,
     *     unitPriceMinorUnits: int,
     *     currency: string
     * }> $prescriptionLines
     * @param list<array{
     *     id: string,
     *     sourceLineId: string,
     *     source: string,
     *     label: string,
     *     code: ?string,
     *     quantity: float,
     *     unitPriceMinorUnits: int,
     *     currency: string,
     *     taxCategoryCode: ?string,
     *     totalMinorUnits: int
     * }> $billingLines
     * @param array{
     *     totalHtMinorUnits: int,
     *     totalTvaMinorUnits: int,
     *     totalTtcMinorUnits: int,
     *     currency: string
     * } $totals
     */
    public function __construct(
        public string $consultationId,
        public string $clinicId,
        public string $practitionerUserId,
        public string $status,
        public ?string $appointmentId,
        public string $admissionId,
        public string $patientId,
        public ?string $chiefComplaint,
        public ?array $vitals,
        public array $notes,
        public array $acts,
        public ?string $summary,
        public string $startedAtUtc,
        public ?string $closedAtUtc,
        public ?string $subjectiveText,
        public ?string $objectiveObservations,
        public ?string $teamMemo,
        public array $motifs,
        public array $typedVitals,
        public array $examSystems,
        public array $diagnoses,
        public array $planActions,
        public array $prescriptionLines,
        public array $billingLines,
        public array $totals,
    ) {
    }

    /**
     * @param array{
     *     totalHtMinorUnits: int,
     *     totalTvaMinorUnits: int,
     *     totalTtcMinorUnits: int,
     *     currency: string
     * } $totals
     */
    public function withTotals(array $totals): self
    {
        return new self(
            $this->consultationId,
            $this->clinicId,
            $this->practitionerUserId,
            $this->status,
            $this->appointmentId,
            $this->admissionId,
            $this->patientId,
            $this->chiefComplaint,
            $this->vitals,
            $this->notes,
            $this->acts,
            $this->summary,
            $this->startedAtUtc,
            $this->closedAtUtc,
            $this->subjectiveText,
            $this->objectiveObservations,
            $this->teamMemo,
            $this->motifs,
            $this->typedVitals,
            $this->examSystems,
            $this->diagnoses,
            $this->planActions,
            $this->prescriptionLines,
            $this->billingLines,
            $totals,
        );
    }

    public function isClosed(): bool
    {
        return 'CLOSED' === $this->status;
    }
}
