<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListAppointmentsForOwner;

use App\Shared\Application\Bus\QueryInterface;

/**
 * Every appointment booked for one owner (client), newest first.
 */
final readonly class ListAppointmentsForOwner implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $ownerId,
        public int $limit = 200,
    ) {
    }
}
