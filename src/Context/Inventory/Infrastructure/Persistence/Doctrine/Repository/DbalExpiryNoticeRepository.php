<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Application\Port\ExpiryNoticeRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalExpiryNoticeRepository implements ExpiryNoticeRepositoryInterface
{
    private const string TABLE = 'inventory__expiry_notifications';

    /** Active lots of tracked, active stock items in the window, never announced. */
    private const string CANDIDATES = " WHERE l.status = 'ACTIVE' AND s.status = 'ACTIVE' AND s.track_stock = 1"
        . ' AND l.expiry_date BETWEEN :from AND :until AND n.lot_id IS NULL';

    public function __construct(private Connection $connection)
    {
    }

    public function clinicsWithUnannouncedLotsExpiringBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT s.clinic_id FROM inventory__lots l'
            . ' JOIN inventory__stock_items s ON s.id = l.stock_item_id'
            . ' LEFT JOIN ' . self::TABLE . ' n ON n.lot_id = l.id'
            . self::CANDIDATES
            . ' ORDER BY s.clinic_id',
            ['from' => $from->format('Y-m-d'), 'until' => $until->format('Y-m-d')],
        );

        return array_map(static fn (mixed $id): string => Uuid::fromBinary(\is_string($id) ? $id : '')->toRfc4122(), $ids);
    }

    public function unannouncedLotsExpiringBetween(string $clinicId, \DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT l.id FROM inventory__lots l'
            . ' JOIN inventory__stock_items s ON s.id = l.stock_item_id'
            . ' LEFT JOIN ' . self::TABLE . ' n ON n.lot_id = l.id'
            . self::CANDIDATES . ' AND s.clinic_id = :clinic'
            . ' ORDER BY l.expiry_date, l.id',
            ['from' => $from->format('Y-m-d'), 'until' => $until->format('Y-m-d'), 'clinic' => Uuid::fromString($clinicId)->toBinary()],
        );

        return array_map(static fn (mixed $id): string => Uuid::fromBinary(\is_string($id) ? $id : '')->toRfc4122(), $ids);
    }

    public function markAnnounced(array $lotIds, \DateTimeImmutable $at): void
    {
        foreach ($lotIds as $lotId) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO ' . self::TABLE . ' (lot_id, notified_at) VALUES (:lot, :at)',
                ['lot' => Uuid::fromString($lotId)->toBinary(), 'at' => $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
            );
        }
    }
}
