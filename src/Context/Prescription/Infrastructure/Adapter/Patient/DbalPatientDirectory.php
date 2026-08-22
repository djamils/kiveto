<?php

declare(strict_types=1);

namespace App\Context\Prescription\Infrastructure\Adapter\Patient;

use App\Context\Prescription\Application\Port\PatientDirectoryInterface;
use App\Context\Prescription\Application\Port\PatientSnapshotDto;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * The animal a consultation treats and the person who answers for it:
 * consultation → patient → animal → primary owner.
 *
 * Read from the ownership and client rows rather than the animal search index,
 * for the same reason billing does: the index is a projection rebuilt after the
 * fact, and an ordonnance has to name its patient the moment it is written.
 */
final readonly class DbalPatientDirectory implements PatientDirectoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function forConsultation(string $consultationId, string $clinicId): ?PatientSnapshotDto
    {
        $row = $this->connection->fetchAssociative(
            'SELECT BIN_TO_UUID(a.id) AS animal_id, a.name, a.species, a.breed_name, a.birth_date,
                    a.microchip_number
             FROM consultation__consultations c
             INNER JOIN patient__patients p ON p.id = c.patient_id
             INNER JOIN animal__animals a ON a.id = p.animal_link_id
             WHERE c.id = :consultationId AND c.clinic_id = :clinicId',
            [
                'consultationId' => Uuid::fromString($consultationId)->toBinary(),
                'clinicId'       => Uuid::fromString($clinicId)->toBinary(),
            ],
        );

        if (false === $row) {
            return null;
        }

        $animalId  = RowAccessor::string($row, 'animal_id');
        $birthDate = RowAccessor::nullableString($row, 'birth_date');
        $owner     = $this->ownerOf($animalId);

        return new PatientSnapshotDto(
            animalId: $animalId,
            animalName: RowAccessor::string($row, 'name'),
            species: RowAccessor::nullableString($row, 'species'),
            breed: RowAccessor::nullableString($row, 'breed_name'),
            birthDate: null !== $birthDate ? new \DateTimeImmutable($birthDate) : null,
            microchipNumber: RowAccessor::nullableString($row, 'microchip_number'),
            ownerClientId: $owner['clientId'],
            ownerName: $owner['name'],
            ownerAddressLines: $owner['addressLines'],
        );
    }

    /**
     * @return array{clientId: ?string, name: ?string, addressLines: list<string>}
     */
    private function ownerOf(string $animalId): array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT BIN_TO_UUID(c.id) AS client_id, c.first_name, c.last_name,
                    c.postal_address_street_line_1, c.postal_address_street_line_2,
                    c.postal_address_postal_code, c.postal_address_city
             FROM animal__ownerships o
             INNER JOIN client__clients c ON c.id = o.client_id
             WHERE o.animal_id = :animalId AND o.role = 'primary' AND o.status = 'active'
             ORDER BY o.started_at ASC
             LIMIT 1",
            ['animalId' => Uuid::fromString($animalId)->toBinary()],
        );

        if (false === $row) {
            return ['clientId' => null, 'name' => null, 'addressLines' => []];
        }

        $name = mb_trim(
            RowAccessor::string($row, 'first_name') . ' ' . RowAccessor::string($row, 'last_name'),
        );

        $lines = [];

        foreach (['postal_address_street_line_1', 'postal_address_street_line_2'] as $column) {
            $line = RowAccessor::nullableString($row, $column);

            if (null !== $line && '' !== mb_trim($line)) {
                $lines[] = mb_trim($line);
            }
        }

        $cityLine = mb_trim(
            (RowAccessor::nullableString($row, 'postal_address_postal_code') ?? '')
            . ' '
            . (RowAccessor::nullableString($row, 'postal_address_city') ?? ''),
        );

        if ('' !== $cityLine) {
            $lines[] = $cityLine;
        }

        return [
            'clientId'     => RowAccessor::string($row, 'client_id'),
            'name'         => '' !== $name ? $name : null,
            'addressLines' => $lines,
        ];
    }
}
