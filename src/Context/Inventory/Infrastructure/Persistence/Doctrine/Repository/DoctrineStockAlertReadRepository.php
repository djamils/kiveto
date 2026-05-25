<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Inventory\Application\Port\StockAlertReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineStockAlertReadRepository implements StockAlertReadRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return list<StockAlertView>
     */
    public function findActive(ClinicId $clinicId, ?StockAlertSeverity $severity): array
    {
        $sql    = 'SELECT * FROM inventory__stock_alerts WHERE clinic_id = :clinicId AND resolved_at IS NULL';
        $params = ['clinicId' => Uuid::fromString($clinicId->toString())->toBinary()];

        if (null !== $severity) {
            $sql .= ' AND severity = :severity';
            $params['severity'] = $severity->value;
        }

        $rows = $this->connection->fetchAllAssociative($sql, $params);

        return array_map(fn (array $row): StockAlertView => $this->rowToView($row), $rows);
    }

    public function findByStockItemAndType(StockItemId $stockItemId, StockAlertType $type): ?StockAlertView
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM inventory__stock_alerts WHERE stock_item_id = :stockItemId AND type = :type AND resolved_at IS NULL ORDER BY detected_at DESC LIMIT 1',
            [
                'stockItemId' => Uuid::fromString($stockItemId->toString())->toBinary(),
                'type'        => $type->value,
            ]
        );

        if (false === $row) {
            return null;
        }

        return $this->rowToView($row);
    }

    public function save(StockAlertView $alert): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT id FROM inventory__stock_alerts WHERE id = :id',
            ['id' => Uuid::fromString($alert->alertId)->toBinary()]
        );

        $params = [
            'clinicId'           => Uuid::fromString($alert->clinicId->toString())->toBinary(),
            'stockItemId'        => Uuid::fromString($alert->stockItemId->toString())->toBinary(),
            'articleId'          => Uuid::fromString($alert->articleId->toString())->toBinary(),
            'articleName'        => $alert->articleName,
            'type'               => $alert->type->value,
            'severity'           => $alert->severity->value,
            'currentLevelAmount' => $alert->currentLevelAmount,
            'currentLevelUnit'   => $alert->currentLevelUnit,
            'earliestExpiry'     => $alert->earliestExpiry?->format('Y-m-d'),
            'detectedAt'         => $alert->detectedAt->format('Y-m-d H:i:s'),
            'resolvedAt'         => $alert->resolvedAt?->format('Y-m-d H:i:s'),
        ];

        if (false === $existing) {
            $this->connection->executeStatement(
                'INSERT INTO inventory__stock_alerts (id, clinic_id, stock_item_id, article_id, article_name, type, severity, current_level_amount, current_level_unit, earliest_expiry, detected_at, resolved_at)
                 VALUES (:id, :clinicId, :stockItemId, :articleId, :articleName, :type, :severity, :currentLevelAmount, :currentLevelUnit, :earliestExpiry, :detectedAt, :resolvedAt)',
                array_merge(['id' => Uuid::fromString($alert->alertId)->toBinary()], $params)
            );
        } else {
            $this->connection->executeStatement(
                'UPDATE inventory__stock_alerts SET severity = :severity, current_level_amount = :currentLevelAmount, current_level_unit = :currentLevelUnit, earliest_expiry = :earliestExpiry, detected_at = :detectedAt, resolved_at = :resolvedAt WHERE id = :id',
                array_merge(['id' => Uuid::fromString($alert->alertId)->toBinary()], $params)
            );
        }
    }

    public function resolve(string $alertId, \DateTimeImmutable $resolvedAt): void
    {
        $this->connection->executeStatement(
            'UPDATE inventory__stock_alerts SET resolved_at = :resolvedAt WHERE id = :id',
            [
                'id'         => Uuid::fromString($alertId)->toBinary(),
                'resolvedAt' => $resolvedAt->format('Y-m-d H:i:s'),
            ]
        );
    }

    /** @param array<string, mixed> $row */
    private function rowToView(array $row): StockAlertView
    {
        $earliestExpiry = null !== $row['earliest_expiry']
            ? new \DateTimeImmutable(RowAccessor::string($row, 'earliest_expiry'))
            : null;

        $resolvedAt = null !== $row['resolved_at']
            ? new \DateTimeImmutable(RowAccessor::string($row, 'resolved_at'))
            : null;

        return new StockAlertView(
            alertId: Uuid::fromBinary(RowAccessor::string($row, 'id'))->toString(),
            clinicId: ClinicId::fromString(Uuid::fromBinary(RowAccessor::string($row, 'clinic_id'))->toString()),
            stockItemId: StockItemId::fromString(Uuid::fromBinary(RowAccessor::string($row, 'stock_item_id'))->toString()),
            articleId: ArticleId::fromString(Uuid::fromBinary(RowAccessor::string($row, 'article_id'))->toString()),
            articleName: RowAccessor::string($row, 'article_name'),
            type: StockAlertType::from(RowAccessor::string($row, 'type')),
            severity: StockAlertSeverity::from(RowAccessor::string($row, 'severity')),
            currentLevelAmount: RowAccessor::nullableString($row, 'current_level_amount'),
            currentLevelUnit: RowAccessor::string($row, 'current_level_unit'),
            earliestExpiry: $earliestExpiry,
            detectedAt: new \DateTimeImmutable(RowAccessor::string($row, 'detected_at')),
            resolvedAt: $resolvedAt,
        );
    }
}
