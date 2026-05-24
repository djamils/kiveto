<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\RenameAct;

use App\Shared\Application\Bus\CommandInterface;

final readonly class RenameAct implements CommandInterface
{
    public function __construct(
        public string $actId,
        public string $clinicId,
        public string $name,
    ) {
    }
}
