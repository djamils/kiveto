<?php

declare(strict_types=1);

namespace App\Animal\Application\Command\ReplaceAnimalOwners;

use App\Shared\Application\Bus\CommandInterface;

final readonly class ReplaceAnimalOwners implements CommandInterface
{
    /**
     * @param list<string> $secondaryOwnerClientIds
     */
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public string $primaryOwnerClientId,
        public array $secondaryOwnerClientIds,
    ) {
    }
}
