<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Application\Port\OccupyingAppointmentsReaderInterface;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalOccupyingAppointmentsReader implements OccupyingAppointmentsReaderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function occupyingBetween(
        ClinicId $clinicId,
        UserId $practitionerUserId,
        \DateTimeImmutable $fromUtc,
        \DateTimeImmutable $toUtc,
        ?AppointmentId $excludeAppointmentId = null,
        bool $currentRead = false,
    ): array {
        $utc = new \DateTimeZone('UTC');
        $sql = <<<'SQL'
            SELECT starts_at_utc, duration_minutes
            FROM scheduling__appointments
            WHERE clinic_id = :clinicId
              AND practitioner_user_id = :practitionerUserId
              AND status IN (:occupying)
              AND starts_at_utc >= :scanFrom
              AND starts_at_utc < :toUtc
              AND DATE_ADD(starts_at_utc, INTERVAL duration_minutes MINUTE) > :fromUtc
        SQL;

        $params = [
            'clinicId'           => Uuid::fromString($clinicId->toString())->toBinary(),
            'practitionerUserId' => Uuid::fromString($practitionerUserId->toString())->toBinary(),
            'fromUtc'            => $fromUtc->setTimezone($utc)->format('Y-m-d H:i:s'),
            'toUtc'              => $toUtc->setTimezone($utc)->format('Y-m-d H:i:s'),
            // No slot is longer than a day: the scan, and its locks, stop there instead of covering the whole history
            'scanFrom' => $fromUtc->setTimezone($utc)->modify(\sprintf('-%d minutes', TimeSlot::MAX_DURATION_MINUTES))->format('Y-m-d H:i:s'),
            // Pending requests hold their slot too
            'occupying' => AppointmentStatus::OCCUPYING_SQL_VALUES,
        ];

        if (null !== $excludeAppointmentId) {
            // The appointment being moved does not block its own new slot
            $sql .= ' AND id != :excludeId';
            $params['excludeId'] = Uuid::fromString($excludeAppointmentId->toString())->toBinary();
        }

        $sql .= ' ORDER BY starts_at_utc';

        if ($currentRead) {
            // Sees bookings committed after this transaction's snapshot
            $sql .= ' FOR SHARE';
        }

        $rows = $this->connection->fetchAllAssociative($sql, $params, ['occupying' => ArrayParameterType::STRING]);

        return array_map(static function (array $row) use ($utc): BusyInterval {
            $start = new \DateTimeImmutable(RowAccessor::string($row, 'starts_at_utc'), $utc);

            return BusyInterval::appointment($start, $start->modify(\sprintf('+%d minutes', RowAccessor::int($row, 'duration_minutes'))));
        }, $rows);
    }
}
