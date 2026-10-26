<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Clinic\Application\Port\VeterinarianDailyCountStoreInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalVeterinarianDailyCountStore implements VeterinarianDailyCountStoreInterface
{
    private const string TABLE = 'clinic__veterinarian_daily_counts';

    public function __construct(private Connection $connection)
    {
    }

    public function record(string $clinicId, \DateTimeImmutable $day, int $count): int
    {
        $params = ['clinic' => Uuid::fromString($clinicId)->toBinary(), 'day' => $day->format('Y-m-d')];

        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (clinic_id, day, count) VALUES (:clinic, :day, :count)'
            . ' ON DUPLICATE KEY UPDATE count = GREATEST(count, VALUES(count))',
            $params + ['count' => max(0, $count)],
        );

        $stored = $this->connection->fetchOne('SELECT count FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND day = :day', $params);
        \assert(is_numeric($stored));

        return (int) $stored;
    }
}
