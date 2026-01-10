<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ChangeClinicStatus;

use App\Clinic\Domain\ValueObject\ClinicStatus;

final readonly class ChangeClinicStatus
{
    public function __construct(
        public string $clinicId,
        public ClinicStatus $status,
    ) {
    }
}
