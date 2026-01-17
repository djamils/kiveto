<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\RenameClinicGroup;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RenameClinicGroup implements CommandInterface
{
    public function __construct(
        public string $clinicGroupId,
        public string $name,
    ) {
    }
}
