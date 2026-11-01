<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListBookingMotifs;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The clinic's booking motifs, by sort order then label → list<BookingMotifView>.
 */
final readonly class ListBookingMotifs implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public bool $includeArchived = false,
    ) {
    }
}
