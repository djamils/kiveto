<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Port;

use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerDay;

/**
 * Transactional per-practitioner-day mutex (S4d). Every write that books,
 * moves, confirms or declines an appointment, or changes a planning block,
 * takes it for the keys built by `LockKeys` before its overlap and capacity
 * checks; the lock is held until the surrounding transaction ends. Once it is
 * held, reads must be current (`…Locked` port methods, `FOR SHARE`).
 *
 * Take it before any other write of the transaction. A deadlock rolls the
 * whole transaction back: it is reported as `SlotTakenException::contended`
 * and the command bus runs the whole command once more.
 */
interface PractitionerDayLockInterface
{
    /**
     * @param list<PractitionerDay> $keys sorted and deduplicated here
     *
     * @throws SlotTakenException (contended) when the lock deadlocks: the transaction is rolled back
     */
    public function lock(ClinicId $clinicId, array $keys): void;

    /**
     * Deletes the rows of days before $localDate (YYYY-MM-DD); returns how many.
     */
    public function purgeBefore(string $localDate): int;
}
