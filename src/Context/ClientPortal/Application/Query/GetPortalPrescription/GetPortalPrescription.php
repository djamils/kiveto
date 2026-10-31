<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Query\GetPortalPrescription;

use App\Shared\Application\Bus\QueryInterface;

/**
 * A prescription the account sees among its documents, with its lines.
 * Answers a PortalPrescription, or null (unpublished, withdrawn, another
 * client's).
 */
final readonly class GetPortalPrescription implements QueryInterface
{
    public function __construct(
        public string $userId,
        public string $clinicId,
        public string $prescriptionId,
    ) {
    }
}
