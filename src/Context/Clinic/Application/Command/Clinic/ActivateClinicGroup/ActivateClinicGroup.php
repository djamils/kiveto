<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\ActivateClinicGroup;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ActivateClinicGroup implements CommandInterface
{
    public function __construct(
        public string $clinicGroupId,
    ) {
    }
}
