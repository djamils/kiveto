<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Adapter\Animal;

use App\Scheduling\Application\Port\AnimalExistenceCheckerInterface;
use App\Scheduling\Domain\ValueObject\AnimalId;
use Doctrine\DBAL\Connection;

final readonly class DbalAnimalExistenceChecker implements AnimalExistenceCheckerInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function exists(AnimalId $animalId): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM animal__animals WHERE id = :animalId';

        $result = $this->connection->fetchAssociative($sql, [
            'animalId' => $animalId->toString(),
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
