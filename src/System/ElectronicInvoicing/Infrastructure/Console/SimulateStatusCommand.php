<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Console;

use App\System\ElectronicInvoicing\Infrastructure\Platform\Simulator\SimulatorDriver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Plays the simulated platform by hand (dev / demo only; refused unless the
 * configured platform is the simulator): emits a status on a deposited
 * document, or applies every pending scenario step at once. The statuses
 * reach Kiveto at the next synchronisation.
 */
#[AsCommand(name: 'app:electronic-invoicing:simulate', description: 'Emit a lifecycle status on the local e-invoicing platform simulator')]
final class SimulateStatusCommand extends Command
{
    public function __construct(private readonly SimulatorDriver $driver)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('sellerSiren', InputArgument::OPTIONAL, 'The seller SIREN (BT-30)')
            ->addArgument('documentNumber', InputArgument::OPTIONAL, 'The invoice / credit note number (BT-1)')
            ->addArgument('statusCode', InputArgument::OPTIONAL, 'The lifecycle status code (e.g. 205, 210)')
            ->addOption('reason', null, InputOption::VALUE_REQUIRED, 'The reason code (e.g. DOUBLON)')
            ->addOption('advance-all', null, InputOption::VALUE_NONE, 'Apply every pending scenario step now')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            if (true === $input->getOption('advance-all')) {
                $io->success(\sprintf('%d status(es) emitted.', $this->driver->advanceAll()));

                return Command::SUCCESS;
            }

            $seller = $input->getArgument('sellerSiren');
            $number = $input->getArgument('documentNumber');
            $code   = $input->getArgument('statusCode');
            $reason = $input->getOption('reason');

            if (!\is_string($seller) || !\is_string($number) || !\is_string($code) || 1 !== preg_match('/^\d{3}$/', $code)) {
                $io->error('Usage: app:electronic-invoicing:simulate <sellerSiren> <documentNumber> <statusCode> [--reason=CODE], or --advance-all.');

                return Command::INVALID;
            }

            $status = $this->driver->emit($seller, $number, (int) $code, \is_string($reason) ? $reason : null);
        } catch (\LogicException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Status %d emitted on %s at %s.', $status->code, $number, $status->at->format(\DateTimeInterface::ATOM)));

        return Command::SUCCESS;
    }
}
