<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Adapter\Animal;

use App\ClinicalCare\Application\Port\AnimalExistenceCheckerInterface;
use App\ClinicalCare\Domain\ValueObject\AnimalId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalAnimalExistenceChecker implements AnimalExistenceCheckerInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function exists(AnimalId $animalId): bool
    {
        $animalBinary = Uuid::fromString($animalId->toString())->toBinary();

        $sql = 'SELECT COUNT(*) as cnt FROM animal__animals WHERE id = :animalId';

        $result = $this->connection->fetchAssociative($sql, [
            'animalId' => $animalBinary,
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
