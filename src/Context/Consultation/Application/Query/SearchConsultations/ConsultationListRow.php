<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Query\SearchConsultations;

/**
 * Repository-level row, before VAT is applied.
 *
 * The read repository stays bus-free and therefore cannot reach the taxation
 * port, so it hands the pre-tax total broken down by tax category and lets the
 * handler turn that into VAT and TTC.
 */
final readonly class ConsultationListRow
{
    /**
     * @param list<string>       $motifs
     * @param array<string, int> $htByTaxCategory tax category code => pre-tax minor units,
     *                                            the empty key holding the untaxed lines
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
        public array $htByTaxCategory,
        public string $currency,
    ) {
    }
}
