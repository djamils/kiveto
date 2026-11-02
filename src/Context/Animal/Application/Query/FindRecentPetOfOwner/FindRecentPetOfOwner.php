<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\FindRecentPetOfOwner;

use App\Shared\Application\Bus\QueryInterface;

/**
 * That animal, when it is still active, alive, not transferred, of that
 * primary owner, with that name (case-insensitive) and species, created at
 * or after `createdSince`: a Portal booking retried with a new animal reuses
 * the one this very request registered (S4g, S4B-01; never one the clinic
 * created). Answers its id, or null.
 */
final readonly class FindRecentPetOfOwner implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public string $primaryOwnerClientId,
        public string $name,
        public string $species,
        public \DateTimeImmutable $createdSince,
    ) {
    }
}
