<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\RenameClinic;

final readonly class RenameClinic
{
    public function __construct(
        public string $clinicId,
        public string $name,
    ) {
    }
}
