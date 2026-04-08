<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Adapter\Animal;

use App\Scheduling\Application\Port\AnimalExistenceCheckerInterface;
use App\Scheduling\Domain\ValueObject\AnimalId;
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
        // The id column is stored as BINARY(16) by Doctrine's UuidType — bind the
        // binary representation, not the RFC 4122 string, otherwise the lookup misses.
        $sql = 'SELECT COUNT(*) as cnt FROM animal__animals WHERE id = :animalId';

        $result = $this->connection->fetchAssociative($sql, [
            'animalId' => Uuid::fromString($animalId->toString())->toBinary(),
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
