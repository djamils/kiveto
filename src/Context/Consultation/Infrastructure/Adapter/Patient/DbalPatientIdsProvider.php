<?php

declare(strict_types=1);

namespace App\Context\Consultation\Infrastructure\Adapter\Patient;

use App\Context\Consultation\Application\Port\PatientIdsProviderInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalPatientIdsProvider implements PatientIdsProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function findPatientIdsForAnimal(string $animalId, string $clinicId): array
    {
        if (!Uuid::isValid($animalId) || !Uuid::isValid($clinicId)) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id FROM patient__patients WHERE clinic_id = :clinicId AND animal_link_id = :animalId',
            [
                'clinicId' => Uuid::fromString($clinicId)->toBinary(),
                'animalId' => Uuid::fromString($animalId)->toBinary(),
            ],
        );

        return array_map(
            /** @param array<string, mixed> $row */
            static fn (array $row): string => RowAccessor::uuid($row, 'id'),
            $rows,
        );
    }

    public function findPatientIdsForAnimals(array $animalIds, string $clinicId): array
    {
        if (!Uuid::isValid($clinicId)) {
            return [];
        }

        $binaryAnimalIds = [];

        foreach ($animalIds as $animalId) {
            if (Uuid::isValid($animalId)) {
                $binaryAnimalIds[] = Uuid::fromString($animalId)->toBinary();
            }
        }

        if ([] === $binaryAnimalIds) {
            return [];
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT id FROM patient__patients WHERE clinic_id = :clinicId AND animal_link_id IN (:animalIds)',
            [
                'clinicId'  => Uuid::fromString($clinicId)->toBinary(),
                'animalIds' => $binaryAnimalIds,
            ],
            ['animalIds' => ArrayParameterType::BINARY],
        );

        return array_map(
            /** @param array<string, mixed> $row */
            static fn (array $row): string => RowAccessor::uuid($row, 'id'),
            $rows,
        );
    }
}
