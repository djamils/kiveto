<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\AllowPrescriptionRenewals;

use App\Shared\Application\Bus\CommandInterface;

final readonly class AllowPrescriptionRenewals implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $prescriptionId,
        public int $count,
    ) {
    }
}
