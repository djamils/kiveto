<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ChangeClinicStatus;

use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicStatus implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public ClinicStatus $status,
    ) {
    }
}
