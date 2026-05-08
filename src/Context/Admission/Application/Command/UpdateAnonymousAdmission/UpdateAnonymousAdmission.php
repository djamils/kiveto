<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Command\UpdateAnonymousAdmission;

use App\Shared\Application\Bus\CommandInterface;

final readonly class UpdateAnonymousAdmission implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $admissionId,
        public ?string $physicalDescription,
        public ?string $triageNotes,
    ) {
    }
}
