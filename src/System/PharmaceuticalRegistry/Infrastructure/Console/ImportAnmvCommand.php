<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\PharmaceuticalRegistry\Application\Command\ImportSnapshot\ImportSnapshot;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:pharmaceutical-registry:import-anmv', description: 'Stage an ANMV XML snapshot (no diff/apply)')]
final class ImportAnmvCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'Path to the ANMV XML file')
            ->addOption('dictionary', null, InputOption::VALUE_REQUIRED, 'Path to the ANMV dictionary XML')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $file */
        $file = $input->getOption('file');
        /** @var string $dictionary */
        $dictionary = $input->getOption('dictionary');

        /** @var string $snapshotId */
        $snapshotId = $this->commandBus->dispatch(new ImportSnapshot(
            source: ImportSource::ANMV->value,
            filePath: $file,
            dictionaryPath: $dictionary,
        ));

        $output->writeln(\sprintf('<info>Snapshot created: %s</info>', $snapshotId));

        return Command::SUCCESS;
    }
}
