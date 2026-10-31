<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\ClientPortal\Application\Port\BookletFileRepositoryInterface;
use Doctrine\DBAL\Connection;

/** One row per animal, upserted; dates are written in UTC. */
final readonly class DbalBookletFileRepository implements BookletFileRepositoryInterface
{
    public const string TABLE = 'client_portal__booklet_files';

    public function __construct(private Connection $connection)
    {
    }

    public function currentKey(string $clinicId, string $animalId): ?string
    {
        $key = $this->connection->fetchOne(
            'SELECT storage_key FROM ' . self::TABLE . ' WHERE animal_id = :animal AND clinic_id = :clinic',
            ['animal' => SqlValues::binary($animalId), 'clinic' => SqlValues::binary($clinicId)],
        );

        return \is_string($key) ? $key : null;
    }

    public function saveCurrent(string $clinicId, string $animalId, string $storageKey, \DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (animal_id, clinic_id, storage_key, updated_at) VALUES (:animal, :clinic, :key, :at)'
            . ' AS new ON DUPLICATE KEY UPDATE clinic_id = new.clinic_id, storage_key = new.storage_key, updated_at = new.updated_at',
            [
                'animal' => SqlValues::binary($animalId),
                'clinic' => SqlValues::binary($clinicId),
                'key'    => $storageKey,
                'at'     => SqlValues::utc($at),
            ],
        );
    }
}
