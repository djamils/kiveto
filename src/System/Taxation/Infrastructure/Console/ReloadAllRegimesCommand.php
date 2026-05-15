<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Taxation\Application\Command\ReloadAllRegimes\ReloadAllRegimes;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:taxation:reload-all', description: 'Reload all tax regimes')]
final class ReloadAllRegimesCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->commandBus->dispatch(new ReloadAllRegimes());
        $output->writeln('Reloaded all regimes.');

        return Command::SUCCESS;
    }
}
