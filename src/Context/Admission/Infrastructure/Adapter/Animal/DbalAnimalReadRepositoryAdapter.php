<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Adapter\Animal;

use App\Context\Admission\Application\Port\AnimalChipView;
use App\Context\Admission\Application\Port\AnimalReadRepositoryPort;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalAnimalReadRepositoryAdapter implements AnimalReadRepositoryPort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function findByMicrochip(string $microchipNumber, string $clinicId): ?AnimalChipView
    {
        $clinicBinary = Uuid::fromString($clinicId)->toBinary();

        $row = $this->connection->fetchAssociative(
            'SELECT BIN_TO_UUID(id) AS animalId, name FROM animal__animals WHERE microchip_number = :chip AND clinic_id = :clinicId AND status = :status LIMIT 1',
            [
                'chip'     => $microchipNumber,
                'clinicId' => $clinicBinary,
                'status'   => 'active',
            ],
        );

        if (false === $row) {
            return null;
        }

        \assert(\is_string($row['animalId']));
        \assert(\is_string($row['name']));

        return new AnimalChipView(
            animalId: $row['animalId'],
            animalName: $row['name'],
        );
    }
}
