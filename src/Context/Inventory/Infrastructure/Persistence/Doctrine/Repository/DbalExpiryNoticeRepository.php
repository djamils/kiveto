<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Application\Port\ExpiryNoticeRepositoryInterface;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DbalExpiryNoticeRepository implements ExpiryNoticeRepositoryInterface
{
    private const string TABLE = 'inventory__expiry_notifications';

    public function __construct(private Connection $connection)
    {
    }

    public function unannouncedLotsExpiringBetween(\DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT l.id AS lot_id, s.clinic_id FROM inventory__lots l'
            . ' JOIN inventory__stock_items s ON s.id = l.stock_item_id'
            . ' LEFT JOIN ' . self::TABLE . ' n ON n.lot_id = l.id'
            . " WHERE l.status = 'ACTIVE' AND s.status = 'ACTIVE' AND s.track_stock = 1"
            . ' AND l.expiry_date BETWEEN :from AND :until AND n.lot_id IS NULL'
            . ' ORDER BY s.clinic_id, l.expiry_date, l.id',
            ['from' => $from->format('Y-m-d'), 'until' => $until->format('Y-m-d')],
        );

        $lots = [];

        foreach ($rows as $row) {
            $lots[RowAccessor::uuid($row, 'clinic_id')][] = RowAccessor::uuid($row, 'lot_id');
        }

        return $lots;
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
