<?php

declare(strict_types=1);

namespace App\ClinicalCare\Infrastructure\Adapter\Client;

use App\ClinicalCare\Application\Port\OwnerExistenceCheckerInterface;
use App\ClinicalCare\Domain\ValueObject\OwnerId;
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

        $sql = 'SELECT COUNT(*) as cnt FROM client__owners WHERE id = :ownerId';

        $result = $this->connection->fetchAssociative($sql, [
            'ownerId' => $ownerBinary,
        ]);

        return ($result['cnt'] ?? 0) > 0;
    }
}
