<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetAppointmentClinicId;

use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetAppointmentClinicIdHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function __invoke(GetAppointmentClinicId $query): ?string
    {
        $sql = 'SELECT BIN_TO_UUID(clinic_id) FROM scheduling__appointments WHERE id = UUID_TO_BIN(:id)';

        /** @var false|string $result */
        $result = $this->connection->fetchOne($sql, ['id' => $query->appointmentId]);

        return false === $result ? null : $result;
    }
}
