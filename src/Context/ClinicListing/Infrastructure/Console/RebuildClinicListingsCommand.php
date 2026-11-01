<?php

declare(strict_types=1);

namespace App\Context\ClinicListing\Infrastructure\Console;

use App\Context\ClinicListing\Application\Command\RebuildClinicListings\RebuildClinicListings;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Run at deploy (and by the dev dataset): every clinic gets its directory
 * projection, re-read from Clinic; changed addresses are queued for geocoding.
 */
#[AsCommand(name: 'clinic-listing:rebuild', description: 'Rebuild the clinic directory projection from the Clinic context')]
final class RebuildClinicListingsCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->commandBus->dispatch(new RebuildClinicListings());
        \assert(\is_int($count));

        new SymfonyStyle($input, $output)->success(\sprintf('%d clinic listing(s) rebuilt.', $count));

        return Command::SUCCESS;
    }
}
