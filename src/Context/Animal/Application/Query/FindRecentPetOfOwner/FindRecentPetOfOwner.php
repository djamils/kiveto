<?php

declare(strict_types=1);

namespace App\Context\Animal\Application\Query\FindRecentPetOfOwner;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The active animal of that primary owner with that name (case-insensitive)
 * and species, created at or after `createdSince`: a booking retried with a
 * new animal reuses it instead of registering it twice (portal spec S4g).
 * Answers its id, or null.
 */
final readonly class FindRecentPetOfOwner implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $primaryOwnerClientId,
        public string $name,
        public string $species,
        public \DateTimeImmutable $createdSince,
    ) {
    }
}
