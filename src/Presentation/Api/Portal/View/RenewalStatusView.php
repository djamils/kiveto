<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

use App\Context\ClientPortal\Application\Query\GetLatestRenewalStatuses\RenewalStatus;

/** `renewalStatus` of a prescription: its latest renewal request. */
final readonly class RenewalStatusView
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

    public static function of(?RenewalStatus $status): ?self
    {
        return null === $status ? null : new self($status->status, $status->requestId, $status->resultingPrescriptionId);
    }
}
