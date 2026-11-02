<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries;

final readonly class PrescriptionPortalSummary
{
    /**
     * @param string  $status          the status as stored (DRAFT, ISSUED, CANCELLED)
     * @param ?string $firstMedication the label of its first line, null when it has none
     */
    public function __construct(
        public string $id,
        public string $status,
        public ?\DateTimeImmutable $validUntil,
        public bool $renewableByOwner,
        public ?string $firstMedication = null,
    ) {
    }
}
