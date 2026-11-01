<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\ListPublishedDocuments;

use App\Context\ClientPortal\Application\Query\GetLatestRenewalStatuses\RenewalStatus;

final readonly class PortalPrescriptionSummary
{
    public function __construct(
        public string $prescriptionId,
        public ?\DateTimeImmutable $validUntil,
        public bool $renewable,
        /** The latest renewal request of the prescription, null without one. */
        public ?RenewalStatus $renewalStatus = null,
    ) {
    }
}
