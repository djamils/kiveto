<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\ReconcileAdmissionPatient;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ReconcileAdmissionPatient implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $admissionId,
        public string $animalId,
        public string $animalName,
    ) {
    }
}
