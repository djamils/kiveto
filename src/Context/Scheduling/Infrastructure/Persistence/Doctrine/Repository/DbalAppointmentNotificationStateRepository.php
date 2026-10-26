<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Scheduling\Domain\Notification\AppointmentNotificationState;
use App\Context\Scheduling\Domain\Notification\AppointmentNotificationStateRepositoryInterface;
use App\Context\Scheduling\Domain\Notification\NotifiedSlot;
use App\Context\Scheduling\Domain\Notification\PendingReminder;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The row of an appointment is created with `INSERT … ON DUPLICATE KEY
 * UPDATE` (no gap lock to race on), then read `FOR UPDATE`: the lock is held
 * until COMMIT, and a request that waited for it reads the rows the previous
 * one committed. Times are stored in UTC.
 */
final readonly class DbalAppointmentNotificationStateRepository implements AppointmentNotificationStateRepositoryInterface
{
    private const string TABLE        = 'scheduling__appointment_notifications';
    private const string APPOINTMENTS = 'scheduling__appointments';
    private const string FORMAT       = 'Y-m-d H:i:s';

    public function __construct(private Connection $connection)
    {
    }

    public function lock(AppointmentId $appointmentId): ?AppointmentNotificationState
    {
        // The clinic comes from the appointment row itself; no row is created for an unknown appointment.
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (appointment_id, clinic_id, silenced, notification_counter)'
            . ' SELECT a.id, a.clinic_id, 0, 0 FROM ' . self::APPOINTMENTS . ' a WHERE a.id = :id'
            . ' ON DUPLICATE KEY UPDATE notification_counter = ' . self::TABLE . '.notification_counter',
            ['id' => self::binary($appointmentId->toString())],
        );

        return $this->findForUpdate($appointmentId);
    }

    public function findForUpdate(AppointmentId $appointmentId): ?AppointmentNotificationState
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE . ' WHERE appointment_id = :id FOR UPDATE',
            ['id' => self::binary($appointmentId->toString())],
        );

        return false === $row ? null : self::state($row);
    }

    public function save(AppointmentNotificationState $state): void
    {
        $notified = $state->notified();

        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (appointment_id, clinic_id, confirmed_at, cancellation_notified_at, silenced,'
            . ' notified_starts_at_utc, notified_duration, notified_practitioner_user_id, notification_counter, reminder_due_at_utc, reminder_requested_at)'
            . ' VALUES (:id, :clinic, :confirmed, :cancelled, :silenced, :start, :duration, :practitioner, :counter, :due, :requested)'
            . ' ON DUPLICATE KEY UPDATE confirmed_at = VALUES(confirmed_at), cancellation_notified_at = VALUES(cancellation_notified_at),'
            . ' silenced = VALUES(silenced), notified_starts_at_utc = VALUES(notified_starts_at_utc), notified_duration = VALUES(notified_duration),'
            . ' notified_practitioner_user_id = VALUES(notified_practitioner_user_id), notification_counter = VALUES(notification_counter),'
            . ' reminder_due_at_utc = VALUES(reminder_due_at_utc), reminder_requested_at = VALUES(reminder_requested_at)',
            [
                'id'           => self::binary($state->appointmentId()->toString()),
                'clinic'       => self::binary($state->clinicId()->toString()),
                'confirmed'    => self::time($state->confirmedAt()),
                'cancelled'    => self::time($state->cancellationNotifiedAt()),
                'silenced'     => $state->isSilenced() ? 1 : 0,
                'start'        => self::time($notified?->startsAtUtc),
                'duration'     => $notified?->durationMinutes,
                'practitioner' => null !== $notified ? self::binary($notified->practitionerUserId) : null,
                'counter'      => $state->notificationCounter(),
                'due'          => self::time($state->reminderDueAt()),
                'requested'    => self::time($state->reminderRequestedAt()),
            ],
        );
    }

    public function silence(AppointmentId $appointmentId, ClinicId $clinicId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (appointment_id, clinic_id, silenced, notification_counter) VALUES (:id, :clinic, 1, 0)'
            . ' ON DUPLICATE KEY UPDATE silenced = 1',
            ['id' => self::binary($appointmentId->toString()), 'clinic' => self::binary($clinicId->toString())],
        );
    }

    public function dueReminders(\DateTimeImmutable $now): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT appointment_id FROM ' . self::TABLE
            . ' WHERE reminder_due_at_utc <= :now AND reminder_requested_at IS NULL ORDER BY reminder_due_at_utc',
            ['now' => self::time($now)],
        );

        return array_map(static fn (mixed $id): string => Uuid::fromBinary(\is_string($id) ? $id : '')->toRfc4122(), $ids);
    }

    public function lockPendingReminders(ClinicId $clinicId, \DateTimeImmutable $now): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT n.*, a.starts_at_utc AS appointment_starts_at_utc FROM ' . self::TABLE . ' n'
            . ' JOIN ' . self::APPOINTMENTS . ' a ON a.id = n.appointment_id'
            . " WHERE n.clinic_id = :clinic AND n.reminder_requested_at IS NULL AND a.status = 'PLANNED' AND a.starts_at_utc > :now"
            . ' FOR UPDATE OF n',
            ['clinic' => self::binary($clinicId->toString()), 'now' => self::time($now)],
        );

        return array_map(
            static fn (array $row): PendingReminder => new PendingReminder(self::state($row), self::utc(RowAccessor::string($row, 'appointment_starts_at_utc'))),
            $rows,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function state(array $row): AppointmentNotificationState
    {
        $start        = RowAccessor::nullableString($row, 'notified_starts_at_utc');
        $practitioner = RowAccessor::nullableUuid($row, 'notified_practitioner_user_id');

        return AppointmentNotificationState::reconstitute(
            appointmentId: AppointmentId::fromString(RowAccessor::uuid($row, 'appointment_id')),
            clinicId: ClinicId::fromString(RowAccessor::uuid($row, 'clinic_id')),
            confirmedAt: self::nullableUtc(RowAccessor::nullableString($row, 'confirmed_at')),
            cancellationNotifiedAt: self::nullableUtc(RowAccessor::nullableString($row, 'cancellation_notified_at')),
            silenced: 1 === RowAccessor::int($row, 'silenced'),
            notified: null !== $start && null !== $practitioner
                ? new NotifiedSlot(self::utc($start), RowAccessor::nullableInt($row, 'notified_duration') ?? 0, $practitioner)
                : null,
            notificationCounter: RowAccessor::int($row, 'notification_counter'),
            reminderDueAt: self::nullableUtc(RowAccessor::nullableString($row, 'reminder_due_at_utc')),
            reminderRequestedAt: self::nullableUtc(RowAccessor::nullableString($row, 'reminder_requested_at')),
        );
    }

    private static function binary(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }

    private static function time(?\DateTimeImmutable $at): ?string
    {
        return $at?->setTimezone(new \DateTimeZone('UTC'))->format(self::FORMAT);
    }

    private static function utc(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    private static function nullableUtc(?string $value): ?\DateTimeImmutable
    {
        return null === $value ? null : self::utc($value);
    }
}
