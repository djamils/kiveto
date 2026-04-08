<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\RenameClinic;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RenameClinic implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $name,
    ) {
    }
}
