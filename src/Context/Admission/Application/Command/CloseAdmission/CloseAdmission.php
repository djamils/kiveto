<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\CloseAdmission;

use App\Shared\Application\Bus\CommandInterface;

final readonly class CloseAdmission implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $admissionId,
        public string $closureReason,
    ) {
    }
}
