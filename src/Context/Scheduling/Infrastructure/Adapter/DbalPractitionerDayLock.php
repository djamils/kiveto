<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\PractitionerDayLockInterface;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\Service\LockKeys;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Symfony\Component\Uid\Uuid;

/**
 * One row per practitioner-day in `scheduling__practitioner_day_locks`,
 * upserted in key order: the row's exclusive lock is held until the
 * transaction ends.
 *
 * A deadlock is not retried here: MySQL has rolled the caller's whole
 * transaction back, earlier writes included, so only the whole command can be
 * run again (the command bus does it once, `CommandBus`).
 */
final readonly class DbalPractitionerDayLock implements PractitionerDayLockInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function lock(ClinicId $clinicId, array $keys): void
    {
        $keys   = LockKeys::merge($keys);
        $clinic = Uuid::fromString($clinicId->toString())->toBinary();

        try {
            foreach ($keys as $key) {
                $this->connection->executeStatement(
                    'INSERT INTO scheduling__practitioner_day_locks (clinic_id, practitioner_user_id, local_date) VALUES (:clinic, :practitioner, :day) ON DUPLICATE KEY UPDATE local_date = local_date',
                    [
                        'clinic'       => $clinic,
                        'practitioner' => Uuid::fromString($key->practitionerId->toString())->toBinary(),
                        'day'          => $key->localDate,
                    ],
                );
            }
        } catch (DeadlockException $deadlock) {
            throw SlotTakenException::contended($deadlock);
        }
    }

    public function purgeBefore(string $localDate): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM scheduling__practitioner_day_locks WHERE local_date < :day',
            ['day' => $localDate],
        );
    }
}
