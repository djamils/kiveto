<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\SuspendClinicGroup;

use App\Shared\Application\Bus\CommandInterface;

final readonly class SuspendClinicGroup implements CommandInterface
{
    public function __construct(
        public string $clinicGroupId,
    ) {
    }
}
