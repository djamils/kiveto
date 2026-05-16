<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycle;
use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycleHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:pharmaceutical-registry:full-import-cycle', description: 'Run full import cycle (stage + diff + apply)')]
final class FullImportCycleCommand extends Command
{
    public function __construct(private readonly RunFullImportCycleHandler $handler)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Import source (e.g. ANMV)', 'ANMV')
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to source XML file')
            ->addOption('dictionary', null, InputOption::VALUE_REQUIRED, 'Path to dictionary XML file')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $source */
        $source = $input->getOption('source');
        /** @var string $file */
        $file = $input->getOption('file');
        /** @var string $dictionary */
        $dictionary = $input->getOption('dictionary');

        $this->handler->handle(new RunFullImportCycle(
            source: $source,
            filePath: $file,
            dictionaryPath: $dictionary,
        ));

        $output->writeln('<info>Full import cycle completed.</info>');

        return Command::SUCCESS;
    }
}
