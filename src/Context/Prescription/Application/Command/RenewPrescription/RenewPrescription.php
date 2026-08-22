<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\RenewPrescription;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RenewPrescription implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $prescriptionId,
        public ?string $renewedByUserId = null,
    ) {
    }
}
