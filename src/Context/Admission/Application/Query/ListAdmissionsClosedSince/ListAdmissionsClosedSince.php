<?php

declare(strict_types=1);

namespace App\Context\Admission\Application\Query\ListAdmissionsClosedSince;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The patients who left the clinic since a point in time (typically the start
 * of the clinic's day), most recent first.
 */
final readonly class ListAdmissionsClosedSince implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public \DateTimeImmutable $since,
    ) {
    }
}
