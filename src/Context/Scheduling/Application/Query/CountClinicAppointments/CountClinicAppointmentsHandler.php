<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\CountClinicAppointments;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CountClinicAppointmentsHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function __invoke(CountClinicAppointments $query): int
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT status, COUNT(*) AS appointments
                FROM scheduling__appointments
                WHERE clinic_id = UUID_TO_BIN(:clinicId)
                  AND starts_at_utc >= :fromUtc
                  AND starts_at_utc <= :toUtc
                GROUP BY status
                SQL,
            [
                'clinicId' => $query->clinicId,
                'fromUtc'  => $query->fromUtc->format('Y-m-d H:i:s'),
                'toUtc'    => $query->toUtc->format('Y-m-d H:i:s'),
            ],
        );

        $count = 0;

        foreach ($rows as $row) {
            if (!AppointmentStatus::from(RowAccessor::string($row, 'status'))->isWithdrawn()) {
                $count += RowAccessor::int($row, 'appointments');
            }
        }

        return $count;
    }
}
