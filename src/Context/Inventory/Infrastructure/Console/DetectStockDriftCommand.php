<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Console;

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
    name: 'app:inventory:detect-stock-drift',
    description: 'Read-only: reports StockItems where sum(lots.quantity) != totalOnHand. No auto-correction.',
)]
final class DetectStockDriftCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
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

        $io->title('Detecting stock drift (read-only)');

        $sql = "SELECT s.id, s.clinic_id, s.total_on_hand_unit,
                       s.total_on_hand_amount,
                       COALESCE(SUM(CASE WHEN l.status = 'ACTIVE' THEN CAST(l.quantity_amount AS DECIMAL(20,6)) ELSE 0 END), 0) AS lot_sum
                FROM inventory__stock_items s
                LEFT JOIN inventory__lots l ON l.stock_item_id = s.id
                WHERE s.track_stock = 1";

        $params = [];

        if (\is_string($clinicId)) {
            $sql .= ' AND s.clinic_id = :clinicId';
            $params['clinicId'] = Uuid::fromString($clinicId)->toBinary();
        }

        $sql .= ' GROUP BY s.id, s.clinic_id, s.total_on_hand_unit, s.total_on_hand_amount';

        $rows       = $this->connection->fetchAllAssociative($sql, $params);
        $driftCount = 0;
        $driftRows  = [];

        foreach ($rows as $row) {
            /** @var numeric-string $totalOnHand */
            $totalOnHand = RowAccessor::string($row, 'total_on_hand_amount');
            $lotSumRaw   = RowAccessor::string($row, 'lot_sum');
            $lotSum      = number_format((float) $lotSumRaw, 6, '.', '');
            $unit        = RowAccessor::string($row, 'total_on_hand_unit');

            if (0 !== bccomp($lotSum, $totalOnHand, 6)) {
                ++$driftCount;
                $driftRows[] = [
                    Uuid::fromBinary(RowAccessor::string($row, 'id'))->toString(),
                    Uuid::fromBinary(RowAccessor::string($row, 'clinic_id'))->toString(),
                    $totalOnHand . ' ' . $unit,
                    $lotSum . ' ' . $unit,
                ];
            }
        }

        if (0 === $driftCount) {
            $io->success('No drift detected. All stock items are consistent.');

            return Command::SUCCESS;
        }

        $io->warning(\sprintf('%d stock item(s) have drift:', $driftCount));
        $io->table(
            ['StockItem ID', 'Clinic ID', 'totalOnHand', 'sum(active lots)'],
            $driftRows,
        );

        return Command::FAILURE;
    }
}
