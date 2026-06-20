<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Application\Query\GetStaleOpenPurchaseOrders\GetStaleOpenPurchaseOrders;
use App\Shared\Application\Bus\QueryBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:procurement:close-stale-orders',
    description: 'Report purchase orders in PARTIALLY_RECEIVED status that have been open for too long. Read-only — does not auto-close.',
)]
final class CloseStaleOrdersCommand extends Command
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Threshold in days before a PARTIALLY_RECEIVED order is considered stale', 60);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $daysRaw = $input->getOption('days');
        $days    = is_numeric($daysRaw) ? (int) $daysRaw : 60;

        $io->title(\sprintf('Stale purchase orders (PARTIALLY_RECEIVED > %d days)', $days));

        /** @var list<array<string, mixed>> $staleOrders */
        $staleOrders = $this->queryBus->ask(new GetStaleOpenPurchaseOrders(daysThreshold: $days));

        if ([] === $staleOrders) {
            $io->success('No stale orders found.');

            return Command::SUCCESS;
        }

        $tableRows = [];

        foreach ($staleOrders as $row) {
            $tableRows[] = [
                \is_string($row['id']) ? $row['id'] : '',
                \is_string($row['orderNumber']) ? $row['orderNumber'] : '',
                \is_string($row['clinicId']) ? $row['clinicId'] : '',
                \is_string($row['submittedAt']) ? $row['submittedAt'] : '',
            ];
        }

        $io->table(['ID', 'Order Number', 'Clinic ID', 'Submitted At'], $tableRows);
        $io->warning(\sprintf('%d stale order(s) detected. Manual review required.', \count($staleOrders)));

        return Command::SUCCESS;
    }
}
