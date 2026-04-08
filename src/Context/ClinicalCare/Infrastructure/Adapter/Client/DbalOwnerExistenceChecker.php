<?php

declare(strict_types=1);

namespace App\Context\ClinicalCare\Infrastructure\Adapter\Client;

use App\Context\ClinicalCare\Application\Port\OwnerExistenceCheckerInterface;
use App\Context\ClinicalCare\Domain\ValueObject\OwnerId;
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
        $ownerBinary = Uuid::fromString($ownerId->toString())->toBinary();

        // In the Client BC the "owner" of a consultation IS a client — there is
        // no separate client__owners table. Query the canonical client__clients table.
        $sql = 'SELECT COUNT(*) as cnt FROM client__clients WHERE id = :ownerId';

        $result = $this->connection->fetchAssociative($sql, [
            'ownerId' => $ownerBinary,
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
