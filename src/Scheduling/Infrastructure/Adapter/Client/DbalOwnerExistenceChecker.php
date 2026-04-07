<?php

declare(strict_types=1);

namespace App\Scheduling\Infrastructure\Adapter\Client;

use App\Scheduling\Application\Port\OwnerExistenceCheckerInterface;
use App\Scheduling\Domain\ValueObject\OwnerId;
use Doctrine\DBAL\Connection;

final readonly class DbalOwnerExistenceChecker implements OwnerExistenceCheckerInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function exists(OwnerId $ownerId): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM client__owners WHERE id = :ownerId';

        $result = $this->connection->fetchAssociative($sql, [
            'ownerId' => $ownerId->toString(),
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
