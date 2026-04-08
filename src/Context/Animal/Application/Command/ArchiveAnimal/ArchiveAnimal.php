<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Command\ArchiveAnimal;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ArchiveAnimal implements CommandInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
    ) {
    }
}
