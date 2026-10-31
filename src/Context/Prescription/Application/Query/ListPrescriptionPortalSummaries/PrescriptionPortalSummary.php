<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries;

final readonly class PrescriptionPortalSummary
{
    /**
     * @param string $status the status as stored (DRAFT, ISSUED, CANCELLED)
     */
    public function __construct(
        public string $id,
        public string $status,
        public ?\DateTimeImmutable $validUntil,
        public bool $renewableByOwner,
    ) {
    }
}
