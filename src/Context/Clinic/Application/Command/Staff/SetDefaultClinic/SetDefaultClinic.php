<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Staff\SetDefaultClinic;

use App\Shared\Application\Bus\CommandInterface;

final readonly class SetDefaultClinic implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $userId,
    ) {
    }
}
