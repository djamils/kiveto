<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Adapter\IdentityAccess;

use App\Context\Clinic\Application\Port\UserExistenceCheckerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalUserExistenceChecker implements UserExistenceCheckerInterface
{
    /**
     * Technical coupling: cross-BC DBAL query on IdentityAccess table.
     * Acceptable at infrastructure layer via port/adapter pattern.
     */
    private const string USER_TABLE_NAME = 'identity_access__users';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function exists(string $userId): bool
    {
        $sql = \sprintf('SELECT 1 FROM %s WHERE id = ? LIMIT 1', self::USER_TABLE_NAME);

        $result = $this->connection->fetchOne($sql, [
            Uuid::fromString($userId)->toBinary(),
        ]);

        return false !== $result;
    }
}
