<?php

declare(strict_types=1);

namespace App\Context\Prescription\Application\Command\IssuePrescription;

use App\Shared\Application\Bus\CommandInterface;

final readonly class IssuePrescription implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $prescriptionId,
    ) {
    }
}
