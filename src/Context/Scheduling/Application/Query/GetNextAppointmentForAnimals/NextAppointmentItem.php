<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals;

/**
 * The next appointment of an animal, without its free-text reason or notes:
 * only the category read from the reason leaves Scheduling.
 */
final readonly class NextAppointmentItem
{
    public function __construct(
        public string $id,
        public string $animalId,
        public \DateTimeImmutable $startsAtUtc,
        public string $practitionerUserId,
        public string $category,
    ) {
    }
}
