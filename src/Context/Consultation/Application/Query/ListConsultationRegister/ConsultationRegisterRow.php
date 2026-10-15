<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\ListConsultationRegister;

/**
 * Repository-level row of the register, before VAT and the facts of other
 * contexts are applied by the handler.
 */
final readonly class ConsultationRegisterRow
{
    /**
     * @param list<string>                              $motifs
     * @param list<array{code: ?string, label: string}> $billedItems     billing lines, in position order
     * @param array<string, int>                        $htByTaxCategory tax category code => pre-tax minor units,
     *                                                                   the empty key holding the untaxed lines
     */
    public function __construct(
        public string $consultationId,
        public string $admissionId,
        public string $patientId,
        public string $practitionerUserId,
        public string $status,
        public string $startedAtUtc,
        public ?string $closedAtUtc,
        public ?string $chiefComplaint,
        public ?string $summary,
        public array $motifs,
        public ?string $primaryDiagnosis,
        public ?string $weightKg,
        public array $billedItems,
        public array $htByTaxCategory,
        public string $currency,
    ) {
    }
}
