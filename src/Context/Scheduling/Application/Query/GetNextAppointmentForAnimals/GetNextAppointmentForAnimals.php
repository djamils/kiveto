<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The earliest planned appointment, starting now or later, of any of the
 * given animals (the Portal home's « next appointment »).
 */
final readonly class GetNextAppointmentForAnimals implements QueryInterface
{
    /**
     * @param list<string> $animalIds
     */
    public function __construct(
        public string $clinicId,
        public array $animalIds,
        public \DateTimeImmutable $nowUtc,
    ) {
    }
}
