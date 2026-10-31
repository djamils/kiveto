<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\GetNextAppointmentForAnimals;

use App\Context\Scheduling\Domain\ValueObject\AppointmentCategory;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GetNextAppointmentForAnimalsHandler
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function __invoke(GetNextAppointmentForAnimals $query): ?NextAppointmentItem
    {
        if ([] === $query->animalIds) {
            return null;
        }

        // The reason is read only to classify the booking; it is never returned.
        $sql = <<<'SQL'
            SELECT
                BIN_TO_UUID(a.id) as id,
                BIN_TO_UUID(a.animal_id) as animal_id,
                a.starts_at_utc,
                BIN_TO_UUID(a.practitioner_user_id) as practitioner_user_id,
                a.reason
            FROM scheduling__appointments a
            WHERE a.clinic_id = UUID_TO_BIN(:clinicId)
              AND a.animal_id IN (:animalIds)
              AND a.status = :status
              AND a.starts_at_utc >= :now
            ORDER BY a.starts_at_utc ASC, a.id ASC
            LIMIT 1
        SQL;

        $row = $this->connection->fetchAssociative(
            $sql,
            [
                'clinicId'  => $query->clinicId,
                'animalIds' => array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $query->animalIds),
                'status'    => AppointmentStatus::PLANNED->value,
                'now'       => $query->nowUtc->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
            ['animalIds' => ArrayParameterType::BINARY],
        );

        if (false === $row) {
            return null;
        }

        return new NextAppointmentItem(
            id: RowAccessor::string($row, 'id'),
            animalId: RowAccessor::string($row, 'animal_id'),
            startsAtUtc: new \DateTimeImmutable(RowAccessor::string($row, 'starts_at_utc'), new \DateTimeZone('UTC')),
            practitionerUserId: RowAccessor::string($row, 'practitioner_user_id'),
            category: AppointmentCategory::fromReason(RowAccessor::nullableString($row, 'reason'))->value,
        );
    }
}
