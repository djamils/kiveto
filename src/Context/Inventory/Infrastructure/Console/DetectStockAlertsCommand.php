<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Console;

use App\Context\Inventory\Application\Port\StockAlertReadRepositoryInterface;
use App\Context\Inventory\Domain\Shared\ValueObject\ArticleId;
use App\Context\Inventory\Domain\Shared\ValueObject\ClinicId;
use App\Context\Inventory\Domain\StockAlert\ReadModel\StockAlertView;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertSeverity;
use App\Context\Inventory\Domain\StockAlert\ValueObject\StockAlertType;
use App\Context\Inventory\Domain\StockItem\ValueObject\StockItemId;
use App\Shared\Infrastructure\Persistence\RowAccessor;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:inventory:detect-alerts',
    description: 'Force full re-projection of stock alerts. Idempotent. Use for debug or post-migration.',
)]
final class DetectStockAlertsCommand extends Command
{
    private const int CRITICAL_DAYS = 7;
    private const int WARNING_DAYS  = 14;
    private const int INFO_DAYS     = 30;

    public function __construct(
        private readonly Connection $connection,
        private readonly StockAlertReadRepositoryInterface $alertRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('clinic', null, InputOption::VALUE_OPTIONAL, 'Restrict to a specific clinic UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $clinicId = $input->getOption('clinic');
        $now      = new \DateTimeImmutable();

        $io->title('Detecting stock alerts');

        $sql    = 'SELECT s.id, s.clinic_id, s.article_id, s.total_on_hand_amount, s.reserved_amount, s.threshold_amount, s.total_on_hand_unit, s.track_stock FROM inventory__stock_items s';
        $params = [];

        if (\is_string($clinicId)) {
            $sql .= ' WHERE s.clinic_id = :clinicId';
            $params['clinicId'] = Uuid::fromString($clinicId)->toBinary();
        }

        $rows  = $this->connection->fetchAllAssociative($sql, $params);
        $count = 0;

        foreach ($rows as $row) {
            if (!(bool) $row['track_stock']) {
                continue;
            }

            $stockItemIdStr = Uuid::fromBinary(RowAccessor::string($row, 'id'))->toString();
            $clinicIdStr    = Uuid::fromBinary(RowAccessor::string($row, 'clinic_id'))->toString();
            $articleIdStr   = Uuid::fromBinary(RowAccessor::string($row, 'article_id'))->toString();
            $stockItemId    = StockItemId::fromString($stockItemIdStr);
            $unit           = RowAccessor::string($row, 'total_on_hand_unit');

            $lotRow = $this->connection->fetchAssociative(
                "SELECT MIN(expiry_date) AS min_expiry FROM inventory__lots WHERE stock_item_id = :id AND status = 'ACTIVE'",
                ['id' => $row['id']]
            );
            $minExpiry         = ($lotRow && $lotRow['min_expiry']) ? new \DateTimeImmutable(RowAccessor::string($lotRow, 'min_expiry')) : null;
            $lowExpirySeverity = null;
            $earliestExpiry    = null;

            if (null !== $minExpiry) {
                $days           = (int) $now->diff($minExpiry)->days;
                $earliestExpiry = $minExpiry;

                if ($days <= self::CRITICAL_DAYS) {
                    $lowExpirySeverity = StockAlertSeverity::CRITICAL;
                } elseif ($days <= self::WARNING_DAYS) {
                    $lowExpirySeverity = StockAlertSeverity::WARNING;
                } elseif ($days <= self::INFO_DAYS) {
                    $lowExpirySeverity = StockAlertSeverity::INFO;
                }
            }

            $this->upsertAlert(
                $stockItemId,
                $clinicIdStr,
                $articleIdStr,
                $unit,
                StockAlertType::LOW_EXPIRY,
                $lowExpirySeverity,
                $now,
                null,
                $earliestExpiry,
            );

            /** @var numeric-string $totalOnHand */
            $totalOnHand = RowAccessor::string($row, 'total_on_hand_amount');
            /** @var numeric-string $thresholdAmount */
            $thresholdAmount = RowAccessor::string($row, 'threshold_amount');
            /** @var numeric-string $reservedAmount */
            $reservedAmount = RowAccessor::string($row, 'reserved_amount');

            $belowThreshold = bccomp($totalOnHand, $thresholdAmount, 6) < 0;
            $this->upsertAlert(
                $stockItemId,
                $clinicIdStr,
                $articleIdStr,
                $unit,
                StockAlertType::LOW_STOCK,
                $belowThreshold ? StockAlertSeverity::WARNING : null,
                $now,
                $totalOnHand,
                null,
            );

            $available  = bcsub($totalOnHand, $reservedAmount, 6);
            $outOfStock = 0 === bccomp($available, '0', 6);
            $this->upsertAlert(
                $stockItemId,
                $clinicIdStr,
                $articleIdStr,
                $unit,
                StockAlertType::OUT_OF_STOCK,
                $outOfStock ? StockAlertSeverity::CRITICAL : null,
                $now,
                $available,
                null,
            );

            ++$count;
        }

        $io->success(\sprintf('Processed %d stock items.', $count));

        return Command::SUCCESS;
    }

    private function upsertAlert(
        StockItemId $stockItemId,
        string $clinicIdStr,
        string $articleIdStr,
        string $unit,
        StockAlertType $type,
        ?StockAlertSeverity $severity,
        \DateTimeImmutable $now,
        ?string $currentLevel,
        ?\DateTimeImmutable $earliestExpiry,
    ): void {
        $existing = $this->alertRepository->findByStockItemAndType($stockItemId, $type);

        if (null === $severity) {
            if (null !== $existing && null === $existing->resolvedAt) {
                $this->alertRepository->resolve($existing->alertId, $now);
            }

            return;
        }

        $articleName = '';

        try {
            $nameRow = $this->connection->fetchOne(
                'SELECT name FROM catalog__articles WHERE id = :id LIMIT 1',
                ['id' => Uuid::fromString($articleIdStr)->toBinary()]
            );
            $articleName = \is_string($nameRow) ? $nameRow : '';
        } catch (\Throwable) {
        }

        $this->alertRepository->save(new StockAlertView(
            alertId: null !== $existing ? $existing->alertId : Uuid::v7()->toRfc4122(),
            clinicId: ClinicId::fromString($clinicIdStr),
            stockItemId: $stockItemId,
            articleId: ArticleId::fromString($articleIdStr),
            articleName: $articleName,
            type: $type,
            severity: $severity,
            currentLevelAmount: $currentLevel,
            currentLevelUnit: $unit,
            earliestExpiry: $earliestExpiry,
            detectedAt: $now,
            resolvedAt: null,
        ));
    }
}
