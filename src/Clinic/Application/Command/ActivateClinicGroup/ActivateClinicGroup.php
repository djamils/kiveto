<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ActivateClinicGroup;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ActivateClinicGroup implements CommandInterface
{
    public function __construct(
        public string $clinicGroupId,
    ) {
    }
}
