<?php

declare(strict_types=1);

namespace App\Context\Catalog\Infrastructure\Console;

use App\Context\Catalog\Application\Command\Catalog\ApplyStarterCatalog\ApplyStarterCatalog;
use App\Context\Catalog\Application\Service\StarterCatalogApplicationReport;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:catalog:apply-starter')]
final class ApplyStarterCatalogCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Apply a starter catalog to a clinic.')
            ->addOption('clinic', null, InputOption::VALUE_REQUIRED, 'Clinic UUID')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Catalog type (companion)', 'companion')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clinicId    = $input->getOption('clinic');
        $catalogType = $input->getOption('type');

        if (!\is_string($clinicId) || '' === $clinicId) {
            $io->error('--clinic option is required.');

            return Command::FAILURE;
        }

        if (!\is_string($catalogType) || '' === $catalogType) {
            $catalogType = 'companion';
        }

        $report = $this->commandBus->dispatch(new ApplyStarterCatalog(
            clinicId: $clinicId,
            catalogType: $catalogType,
        ));

        if (!$report instanceof StarterCatalogApplicationReport) {
            $io->error('Unexpected response from command bus.');

            return Command::FAILURE;
        }

        $io->success(\sprintf('Starter catalog applied. Created: %d, Skipped: %d, Failed: %d', \count($report->created), \count($report->skipped), \count($report->failed)));

        if ([] !== $report->failed) {
            $io->warning('Failed items: ' . implode(', ', $report->failed));
        }

        return Command::SUCCESS;
    }
}
