<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\RemovePrescriptionLine;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RemovePrescriptionLine implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $prescriptionId,
        public string $lineId,
    ) {
    }
}
