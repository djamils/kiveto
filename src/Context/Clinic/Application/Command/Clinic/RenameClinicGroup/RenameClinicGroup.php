<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\RenameClinicGroup;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RenameClinicGroup implements CommandInterface
{
    public function __construct(
        public string $clinicGroupId,
        public string $name,
    ) {
    }
}
