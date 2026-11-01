<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

interface PlanningBlockOccurrencesReaderInterface
{
    /**
     * Every occurrence of every block of the practitioner (all types, any
     * anchor date) on the clinic-local dates [fromDate, toDate], ordered by
     * date then start. `currentRead` reads `FOR SHARE`, for a check under the
     * practitioner-day lock.
     *
     * @return list<PlanningBlockOccurrence>
     */
    public function occurrencesBetween(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        string $fromDate,
        string $toDate,
        bool $currentRead = false,
    ): array;
}
