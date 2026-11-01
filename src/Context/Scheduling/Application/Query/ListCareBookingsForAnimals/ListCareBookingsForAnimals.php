<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The appointments of the animals that hold their slot, start at or after
 * `fromUtc` and were booked for a motif carrying a preventive care kind
 * (the Portal's « booked » care items) → list<CareBookingItem>, soonest first.
 */
final readonly class ListCareBookingsForAnimals implements QueryInterface
{
    /**
     * @param list<string> $animalIds
     */
    public function __construct(
        public string $clinicId,
        public array $animalIds,
        public \DateTimeImmutable $fromUtc,
    ) {
    }
}
