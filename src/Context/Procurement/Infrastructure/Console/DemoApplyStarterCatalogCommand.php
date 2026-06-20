<?php

declare(strict_types=1);

namespace App\Context\Procurement\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:demo:apply-starter-catalog',
    description: 'Apply a starter catalog to a demo clinic. Delegates to Catalog BC if available.',
)]
final class DemoApplyStarterCatalogCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clinic', null, InputOption::VALUE_REQUIRED, 'Clinic UUID')
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Catalog type (companion)', 'companion')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $clinicId = $input->getOption('clinic');
        $type     = $input->getOption('type');

        if (!\is_string($clinicId) || '' === $clinicId) {
            $io->error('--clinic option is required.');

            return Command::FAILURE;
        }

        if (!\is_string($type) || '' === $type) {
            $type = 'companion';
        }

        // Guard: check the cross-BC command exists before dispatching
        $commandClass = \App\Context\Catalog\Application\Command\Catalog\ApplyStarterCatalog\ApplyStarterCatalog::class;

        if (!class_exists($commandClass)) {
            $io->warning('ApplyStarterCatalog command not found in Catalog BC. Skipping.');

            return Command::SUCCESS;
        }

        try {
            $this->commandBus->dispatch(new $commandClass(
                clinicId: $clinicId,
                catalogType: $type,
            ));
        } catch (\Throwable $e) {
            $io->error(\sprintf('Failed to apply starter catalog: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Starter catalog "%s" applied to clinic %s.', $type, $clinicId));

        return Command::SUCCESS;
    }
}
