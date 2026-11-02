<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Domain\Booking\BookedPetMarkerRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/** One row per marker; dates in UTC. */
final readonly class DbalBookedPetMarkerRepository implements BookedPetMarkerRepositoryInterface
{
    public const string TABLE = 'client_portal__booked_pet_markers';

    public function __construct(private Connection $connection)
    {
    }

    public function animalFor(string $marker, \DateTimeImmutable $since): ?string
    {
        $animalId = $this->connection->fetchOne(
            'SELECT animal_id FROM ' . self::TABLE . ' WHERE marker = :marker AND created_at >= :since',
            ['marker' => $marker, 'since' => SqlValues::utc($since)],
        );

        return \is_string($animalId) ? Uuid::fromBinary($animalId)->toRfc4122() : null;
    }

    public function remember(string $marker, string $animalId, \DateTimeImmutable $now, \DateTimeImmutable $expiredBefore): void
    {
        $this->connection->executeStatement('DELETE FROM ' . self::TABLE . ' WHERE created_at < :expired', ['expired' => SqlValues::utc($expiredBefore)]);
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (marker, animal_id, created_at) VALUES (:marker, :animal, :now)'
            . ' ON DUPLICATE KEY UPDATE animal_id = VALUES(animal_id), created_at = VALUES(created_at)',
            ['marker' => $marker, 'animal' => SqlValues::binary($animalId), 'now' => SqlValues::utc($now)],
        );
    }
}
