<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\PharmaceuticalRegistry\Application\Command\CalculateSnapshotDiff\CalculateSnapshotDiff;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:pharmaceutical-registry:diff', description: 'Calculate diff for a snapshot')]
final class CalculateDiffCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('snapshot', null, InputOption::VALUE_REQUIRED, 'Snapshot UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $snapshotId */
        $snapshotId = $input->getOption('snapshot');

        $this->commandBus->dispatch(new CalculateSnapshotDiff(snapshotId: $snapshotId));

        $output->writeln('<info>Diff calculated.</info>');

        return Command::SUCCESS;
    }
}
