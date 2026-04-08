<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Adapter\Client;

use App\Context\Scheduling\Application\Port\OwnerExistenceCheckerInterface;
use App\Context\Scheduling\Domain\ValueObject\OwnerId;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalOwnerExistenceChecker implements OwnerExistenceCheckerInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function exists(OwnerId $ownerId): bool
    {
        // In the Client BC the "owner" of an appointment IS a client — there is
        // no separate client__owners table. Query the canonical client__clients table.
        // The id column is stored as BINARY(16) by Doctrine's UuidType — bind the
        // binary representation, not the RFC 4122 string.
        $sql = 'SELECT COUNT(*) as cnt FROM client__clients WHERE id = :ownerId';

        $result = $this->connection->fetchAssociative($sql, [
            'ownerId' => Uuid::fromString($ownerId->toString())->toBinary(),
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
