<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Query\GetPrescriptionDetails;

use App\Shared\Application\Bus\QueryInterface;

final readonly class GetPrescriptionDetails implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $prescriptionId,
    ) {
    }
}
