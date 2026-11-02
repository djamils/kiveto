<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Service;

use App\Context\Scheduling\Application\Port\OccupyingAppointmentsReaderInterface;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\Service\PlanningBlockOccurrences;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;

/**
 * The occupying appointments of a staff member over the occurrences of a
 * block a mutation concerns, read under the practitioner-day lock: current
 * up to where the lock reaches, plain beyond it (FU-01, open-ended blocks).
 */
final class BlockAppointments
{
    /**
     * @return list<BusyInterval>
     */
    public static function read(
        OccupyingAppointmentsReaderInterface $reader,
        ClinicId $clinicId,
        UserId $staffUserId,
        PlanningBlockOccurrences $occurrences,
    ): array {
        $lockedUntil = $occurrences->lockedUntilUtc();
        $locked      = $reader->occupyingBetween($clinicId, $staffUserId, $occurrences->fromUtc(), $lockedUntil, currentRead: true);

        if ($lockedUntil >= $occurrences->toUtc()) {
            return $locked;
        }

        // Beyond the locked days: a plain read, which waits on no writer of days the lock does not cover
        return [...$locked, ...$occurrences->beyondLock($reader->occupyingBetween($clinicId, $staffUserId, $lockedUntil, $occurrences->toUtc()))];
    }
}
