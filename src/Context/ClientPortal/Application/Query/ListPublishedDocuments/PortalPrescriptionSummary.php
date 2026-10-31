<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPublishedDocuments;

final readonly class PortalPrescriptionSummary
{
    public function __construct(
        public string $prescriptionId,
        public ?\DateTimeImmutable $validUntil,
        public bool $renewable,
    ) {
    }
}
