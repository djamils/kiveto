<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\ChangeClinicTimeZone;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ChangeClinicTimeZone implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $timeZone,
    ) {
    }
}
