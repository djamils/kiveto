<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Scheduling;

use App\Context\Consultation\Application\Port\AppointmentContextDTO;
use App\Context\Consultation\Application\Port\SchedulingAppointmentContextProviderInterface;
use App\Context\Consultation\Domain\ValueObject\AppointmentId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalSchedulingAppointmentContextProvider implements SchedulingAppointmentContextProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function getAppointmentContext(AppointmentId $appointmentId): AppointmentContextDTO
    {
        $appointmentBinary = Uuid::fromString($appointmentId->toString())->toBinary();

        $sql = '
            SELECT
                a.clinic_id,
                a.status,
                a.linked_admission_id
            FROM scheduling__appointments a
            WHERE a.id = :appointmentId
        ';

        $result = $this->connection->fetchAssociative($sql, [
            'appointmentId' => $appointmentBinary,
        ]);

        if (false === $result) {
            throw new \DomainException('Appointment not found');
        }

        return new AppointmentContextDTO(
            clinicId: RowAccessor::uuid($result, 'clinic_id'),
            admissionId: RowAccessor::nullableUuid($result, 'linked_admission_id'),
            status: RowAccessor::string($result, 'status'),
        );
    }
}
