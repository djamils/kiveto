<?php

declare(strict_types=1);

namespace App\Context\Patient\Infrastructure\Adapter\Animal;

use App\Context\Patient\Application\Port\AnimalNameProviderInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineAnimalNameProvider implements AnimalNameProviderInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function getAnimalName(string $animalId, string $clinicId): ?string
    {
        $animalBinary = Uuid::fromString($animalId)->toBinary();
        $clinicBinary = Uuid::fromString($clinicId)->toBinary();

        $result = $this->connection->fetchOne(
            'SELECT name FROM animal__animals WHERE id = :id AND clinic_id = :clinicId',
            [
                'id'       => $animalBinary,
                'clinicId' => $clinicBinary,
            ],
        );

        if (false === $result) {
            return null;
        }

        \assert(\is_string($result));

        return $result;
    }
}
