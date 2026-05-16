<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\Shared\Domain\Time\ClockInterface;
use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycle;
use App\System\PharmaceuticalRegistry\Application\Command\RunFullImportCycle\RunFullImportCycleHandler;
use App\System\PharmaceuticalRegistry\Domain\ValueObject\ImportSource;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:pharmaceutical-registry:full-import-cycle', description: 'Run full import cycle (stage + diff + apply). Reads from storage/pharma-registry/{country}/current/ by default.')]
final class FullImportCycleCommand extends Command
{
    public function __construct(
        private readonly RunFullImportCycleHandler $handler,
        private readonly ImportFileManager $importFileManager,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_OPTIONAL, 'Import source (e.g. ANMV)', 'ANMV')
            ->addOption('file', null, InputOption::VALUE_OPTIONAL, '[debug] Override amm.xml path')
            ->addOption('dictionary', null, InputOption::VALUE_OPTIONAL, '[debug] Override dict.xml path')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $sourceValue */
        $sourceValue = $input->getOption('source');
        $source      = ImportSource::from($sourceValue);

        /** @var string|null $fileOverride */
        $fileOverride = $input->getOption('file');
        /** @var string|null $dictOverride */
        $dictOverride    = $input->getOption('dictionary');
        $isDebugOverride = null !== $fileOverride || null !== $dictOverride;

        $file       = $this->importFileManager->resolveFile($source, 'amm.xml', $fileOverride);
        $dictionary = $this->importFileManager->resolveFile($source, 'dict.xml', $dictOverride);

        if (!file_exists($file)) {
            $output->writeln(\sprintf('<error>File not found: %s</error>', $file));
            $output->writeln('<comment>Place amm.xml and dict.xml in storage/pharma-registry/' . $source->value . '/current/ before importing.</comment>');

            return Command::FAILURE;
        }

        if (!file_exists($dictionary)) {
            $output->writeln(\sprintf('<error>Dictionary not found: %s</error>', $dictionary));

            return Command::FAILURE;
        }

        $output->writeln('<info>Running full import cycle...</info>');

        $this->handler->handle(new RunFullImportCycle(
            source: $source->value,
            filePath: $file,
            dictionaryPath: $dictionary,
        ));

        $output->writeln('<info>Import complete.</info>');

        if (!$isDebugOverride) {
            $this->importFileManager->archiveCurrentFiles($source, $this->clock->now());
            $output->writeln('<info>Files archived.</info>');
        }

        return Command::SUCCESS;
    }
}
