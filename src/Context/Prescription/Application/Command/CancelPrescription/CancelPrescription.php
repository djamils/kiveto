<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\CancelPrescription;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CancelPrescription implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $prescriptionId,
        public string $reason,
    ) {
    }
}
