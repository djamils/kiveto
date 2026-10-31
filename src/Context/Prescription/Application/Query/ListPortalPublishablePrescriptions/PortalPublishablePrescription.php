<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\ListPortalPublishablePrescriptions;

final readonly class PortalPublishablePrescription
{
    public function __construct(
        public string $prescriptionId,
        public string $clinicId,
    ) {
    }
}
