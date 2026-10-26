<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Persistence\Doctrine\Repository;

use App\System\Notification\Domain\Quota\VeterinarianCountRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalVeterinarianCountRepository implements VeterinarianCountRepositoryInterface
{
    private const string TABLE = 'notification__veterinarian_counts';

    public function __construct(private Connection $connection)
    {
    }

    public function record(string $clinicId, \DateTimeImmutable $day, int $count): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE . ' (clinic_id, day, count) VALUES (:clinic, :day, :count)'
            . ' ON DUPLICATE KEY UPDATE count = VALUES(count)',
            ['clinic' => Uuid::fromString($clinicId)->toBinary(), 'day' => $day->format('Y-m-d'), 'count' => max(0, $count)],
        );
    }

    public function maxBetween(string $clinicId, \DateTimeImmutable $firstDay, \DateTimeImmutable $lastDay): ?int
    {
        return self::intOrNull($this->connection->fetchOne(
            'SELECT MAX(count) FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND day BETWEEN :first AND :last',
            ['clinic' => Uuid::fromString($clinicId)->toBinary(), 'first' => $firstDay->format('Y-m-d'), 'last' => $lastDay->format('Y-m-d')],
        ));
    }

    public function lastKnownBefore(string $clinicId, \DateTimeImmutable $day): ?int
    {
        return self::intOrNull($this->connection->fetchOne(
            'SELECT count FROM ' . self::TABLE . ' WHERE clinic_id = :clinic AND day < :day ORDER BY day DESC LIMIT 1',
            ['clinic' => Uuid::fromString($clinicId)->toBinary(), 'day' => $day->format('Y-m-d')],
        ));
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
