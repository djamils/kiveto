<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\Shared\Application\Bus\QueryBusInterface;
use App\System\PharmaceuticalRegistry\Application\Query\GetImportHistory\GetImportHistory;
use App\System\PharmaceuticalRegistry\Application\Query\GetImportHistory\ImportHistoryView;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:pharmaceutical-registry:list-snapshots', description: 'List recent import snapshots')]
final class ListSnapshotsCommand extends Command
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Import source', 'ANMV')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Max results', 20)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $source */
        $source = $input->getOption('source');
        /** @var string|int $limitRaw */
        $limitRaw = $input->getOption('limit');
        $limit    = (int) $limitRaw;

        /** @var ImportHistoryView[] $views */
        $views = $this->queryBus->ask(new GetImportHistory(
            source: $source,
            limit: $limit,
        ));

        $table = new Table($output);
        $table->setHeaders(['ID', 'Source', 'Status', 'Downloaded At', 'Applied At']);

        foreach ($views as $view) {
            $table->addRow([
                $view->id,
                $view->source,
                $view->status,
                $view->downloadedAt->format('Y-m-d H:i:s'),
                $view->appliedAt?->format('Y-m-d H:i:s') ?? '-',
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
