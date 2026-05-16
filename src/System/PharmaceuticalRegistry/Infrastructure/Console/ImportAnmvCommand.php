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
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly ImportFileManager $importFileManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, '[debug] Override amm.xml path')
            ->addOption('dictionary', null, InputOption::VALUE_OPTIONAL, '[debug] Override dict.xml path')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string|null $fileOverride */
        $fileOverride = $input->getOption('file');
        /** @var string|null $dictOverride */
        $dictOverride = $input->getOption('dictionary');

        $file       = $this->importFileManager->resolveFile(ImportSource::ANMV, 'amm.xml', $fileOverride);
        $dictionary = $this->importFileManager->resolveFile(ImportSource::ANMV, 'dict.xml', $dictOverride);

        if (!file_exists($file)) {
            $output->writeln(\sprintf('<error>File not found: %s</error>', $file));
            $output->writeln('<comment>Place amm.xml and dict.xml in storage/pharma-registry/france/current/ before importing.</comment>');

            return Command::FAILURE;
        }

        if (!file_exists($dictionary)) {
            $output->writeln(\sprintf('<error>Dictionary not found: %s</error>', $dictionary));

            return Command::FAILURE;
        }

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
