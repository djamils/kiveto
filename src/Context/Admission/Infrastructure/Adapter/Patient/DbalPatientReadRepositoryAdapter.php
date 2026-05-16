<?php

declare(strict_types=1);

namespace App\Context\Admission\Infrastructure\Adapter\Patient;

use App\Context\Admission\Application\Port\PatientReadRepositoryPort;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalPatientReadRepositoryAdapter implements PatientReadRepositoryPort
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function existsActiveForAnimal(string $clinicId, string $animalId): bool
    {
        return null !== $this->getActivePatientIdForAnimal($clinicId, $animalId);
    }

    public function getActivePatientIdForAnimal(string $clinicId, string $animalId): ?string
    {
        $clinicBinary = Uuid::fromString($clinicId)->toBinary();
        $animalBinary = Uuid::fromString($animalId)->toBinary();

        $row = $this->connection->fetchAssociative(
            'SELECT BIN_TO_UUID(id) AS patientId FROM patient__patients'
                . ' WHERE clinic_id = :clinicId AND animal_link_id = :animalId AND status = :status LIMIT 1',
            [
                'clinicId' => $clinicBinary,
                'animalId' => $animalBinary,
                'status'   => 'active',
            ],
        );

        if (false === $row) {
            return null;
        }

        \assert(\is_string($row['patientId']));

        return $row['patientId'];
    }
}
