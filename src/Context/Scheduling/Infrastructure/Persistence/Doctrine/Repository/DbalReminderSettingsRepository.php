<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Notification\ReminderDelay;
use App\Context\Scheduling\Domain\Notification\ReminderSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * One row per clinic that changed its settings; none means the defaults.
 */
final readonly class DbalReminderSettingsRepository implements ReminderSettingsRepositoryInterface
{
    private const string TABLE = 'scheduling__clinic_settings';

    public function __construct(private Connection $connection)
    {
    }

    public function reminderDelay(ClinicId $clinicId): ReminderDelay
    {
        $delay = $this->connection->fetchOne(
            'SELECT reminder_delay FROM ' . self::TABLE . ' WHERE clinic_id = :clinic',
            ['clinic' => Uuid::fromString($clinicId->toString())->toBinary()],
        );

        return (\is_string($delay) ? ReminderDelay::tryFrom($delay) : null) ?? ReminderDelay::default();
    }

    public function saveReminderDelay(ClinicId $clinicId, ReminderDelay $delay): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (clinic_id, reminder_delay) VALUES (:clinic, :delay)'
            . ' ON DUPLICATE KEY UPDATE reminder_delay = VALUES(reminder_delay)',
            ['clinic' => Uuid::fromString($clinicId->toString())->toBinary(), 'delay' => $delay->value],
        );
    }
}
