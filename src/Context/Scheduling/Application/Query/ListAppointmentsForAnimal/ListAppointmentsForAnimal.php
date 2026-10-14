<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForAnimal;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Every appointment booked for one animal, newest first.
 */
final readonly class ListAppointmentsForAnimal implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $animalId,
        public int $limit = 200,
    ) {
    }
}
