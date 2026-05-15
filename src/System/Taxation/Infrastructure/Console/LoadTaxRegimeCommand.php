<?php

declare(strict_types=1);

namespace App\System\Taxation\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\Taxation\Application\Command\LoadTaxRegime\LoadTaxRegime;
use App\System\Taxation\Domain\ValueObject\TaxRegimeId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:taxation:load-regime', description: 'Load a tax regime from YAML')]
final class LoadTaxRegimeCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('regimeId', InputArgument::REQUIRED, 'Regime ID (e.g. FR)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $regimeIdRaw */
        $regimeIdRaw = $input->getArgument('regimeId');
        $regimeId    = TaxRegimeId::fromString($regimeIdRaw);
        $this->commandBus->dispatch(new LoadTaxRegime($regimeId));
        $output->writeln(\sprintf('Loaded regime %s.', $regimeId->toString()));

        return Command::SUCCESS;
    }
}
