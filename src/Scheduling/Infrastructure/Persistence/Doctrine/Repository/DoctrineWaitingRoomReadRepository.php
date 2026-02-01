<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Persistence\Doctrine\Repository;

use App\Scheduling\Application\Port\WaitingRoomReadRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AppointmentId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\WaitingRoomEntryStatus;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

final readonly class DoctrineWaitingRoomReadRepository implements WaitingRoomReadRepositoryInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function hasActiveEntryForAppointment(ClinicId $clinicId, AppointmentId $appointmentId): bool
    {
        $sql = <<<'SQL'
            SELECT COUNT(*) as cnt
            FROM scheduling__waiting_room_entries
            WHERE clinic_id = :clinicId
              AND linked_appointment_id = :appointmentId
              AND status IN (:activeStatuses)
        SQL;

        $result = $this->connection->fetchAssociative($sql, [
            'clinicId'       => $clinicId->toString(),
            'appointmentId'  => $appointmentId->toString(),
            'activeStatuses' => [
                WaitingRoomEntryStatus::WAITING->value,
                WaitingRoomEntryStatus::CALLED->value,
                WaitingRoomEntryStatus::IN_SERVICE->value,
            ],
        ], [
            'activeStatuses' => ArrayParameterType::STRING,
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }

    public function getActiveStatuses(): array
    {
        return [
            WaitingRoomEntryStatus::WAITING,
            WaitingRoomEntryStatus::CALLED,
            WaitingRoomEntryStatus::IN_SERVICE,
        ];
    }
}
