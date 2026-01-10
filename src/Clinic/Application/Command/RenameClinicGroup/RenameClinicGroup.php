<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\RenameClinicGroup;

final readonly class RenameClinicGroup
{
    public function __construct(
        public string $clinicGroupId,
        public string $name,
    ) {
    }
}
