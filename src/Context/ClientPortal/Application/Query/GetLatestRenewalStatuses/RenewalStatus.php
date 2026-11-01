<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetLatestRenewalStatuses;

/** The latest renewal request of a prescription, as the owner's document list shows it. */
final readonly class RenewalStatus
{
    /**
     * @param string $status submitted|done|rejected|closed
     */
    public function __construct(
        public string $status,
        public string $requestId,
        public ?string $resultingPrescriptionId,
    ) {
    }
}
