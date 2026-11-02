<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Lock;

use App\Context\ClientPortal\Application\Port\PortalLockInterface;
use Doctrine\DBAL\Connection;

/**
 * One row per key in `client_portal__locks`, upserted: the row's exclusive
 * lock is held until the transaction ends. A first lock of a key inserts
 * the row; a concurrent one waits on that uncommitted row (a single unique
 * key, no gap lock), so it never deadlocks.
 */
final readonly class DbalPortalLock implements PortalLockInterface
{
    public const string TABLE = 'client_portal__locks';

    public function __construct(private Connection $connection)
    {
    }

    public function lockAccount(string $userId): void
    {
        $this->lock('account:' . $userId);
    }

    public function lockPetChanges(string $clinicId, string $petId): void
    {
        $this->lock('pet-changes:' . $clinicId . ':' . $petId);
    }

    private function lock(string $key): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (lock_key) VALUES (:key) ON DUPLICATE KEY UPDATE lock_key = lock_key',
            ['key' => $key],
        );
    }
}
