<?php

declare(strict_types=1);

namespace App\Context\Inventory\Infrastructure\Console;

use App\Context\Inventory\Application\Command\ExpireOutdatedLots\ExpireOutdatedLots;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:inventory:expire-lots',
    description: 'Expire ACTIVE lots whose expiry date is in the past. Idempotent — safe to run multiple times.',
)]
final class ExpireOutdatedLotsCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('clinic', null, InputOption::VALUE_OPTIONAL, 'Restrict to a specific clinic UUID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $clinicId = $input->getOption('clinic');
        $start    = microtime(true);

        $io->title('Expiring outdated lots');

        /** @var int $processedCount */
        $processedCount = $this->commandBus->dispatch(new ExpireOutdatedLots(clinicId: \is_string($clinicId) ? $clinicId : null));

        $elapsed = round(microtime(true) - $start, 2);
        $io->success(\sprintf('Processed %d stock items in %ss.', $processedCount, $elapsed));

        return Command::SUCCESS;
    }
}
