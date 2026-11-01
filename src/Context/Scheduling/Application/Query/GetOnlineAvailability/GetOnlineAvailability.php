<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetOnlineAvailability;

use App\Shared\Application\Bus\QueryInterface;

/**
 * The online slots of a practitioner for a motif over clinic-local days
 * `fromLocalDate` … `toLocalDate` (`YYYY-MM-DD`, inclusive, at most 14 days,
 * from the clinic's today on). `newClient` asks for the slots of a booking
 * that will wait for the clinic's confirmation (manual lead time).
 */
final readonly class GetOnlineAvailability implements QueryInterface
{
    public function __construct(
        public string $clinicId,
        public string $motifId,
        public string $practitionerId,
        public string $fromLocalDate,
        public string $toLocalDate,
        public bool $newClient = false,
    ) {
    }
}
