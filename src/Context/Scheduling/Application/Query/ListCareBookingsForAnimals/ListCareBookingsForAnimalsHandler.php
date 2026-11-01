<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Application\Query\ListCareBookingsForAnimals;

use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class ListCareBookingsForAnimalsHandler
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<CareBookingItem>
     */
    public function __invoke(ListCareBookingsForAnimals $query): array
    {
        if ([] === $query->animalIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT BIN_TO_UUID(a.animal_id) AS animal_id, a.starts_at_utc, m.preventive_care_kind
                FROM scheduling__appointments a
                JOIN scheduling__booking_motifs m ON m.id = a.booking_motif_id AND m.clinic_id = a.clinic_id
                WHERE a.clinic_id = UUID_TO_BIN(:clinicId)
                  AND a.animal_id IN (:animalIds)
                  AND a.status IN (:occupying)
                  AND a.starts_at_utc >= :from
                  AND m.preventive_care_kind IS NOT NULL
                ORDER BY a.starts_at_utc ASC, a.id ASC
                SQL,
            [
                'clinicId'  => $query->clinicId,
                'animalIds' => array_map(static fn (string $id): string => Uuid::fromString($id)->toBinary(), $query->animalIds),
                'occupying' => AppointmentStatus::OCCUPYING_SQL_VALUES,
                'from'      => $query->fromUtc->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ],
            ['animalIds' => ArrayParameterType::BINARY, 'occupying' => ArrayParameterType::STRING],
        );

        return array_map(
            static fn (array $row): CareBookingItem => new CareBookingItem(
                RowAccessor::string($row, 'animal_id'),
                new \DateTimeImmutable(RowAccessor::string($row, 'starts_at_utc'), new \DateTimeZone('UTC')),
                RowAccessor::string($row, 'preventive_care_kind'),
            ),
            $rows,
        );
    }
}
