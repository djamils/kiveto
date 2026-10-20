<?php

declare(strict_types=1);

namespace App\System\ElectronicInvoicing\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\ElectronicInvoicing\Application\Command\OpenPlatformConnection\OpenPlatformConnection;
use App\System\ElectronicInvoicing\Application\Command\VerifyPlatformConnection\VerifyPlatformConnection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Connects an organisation (SIREN) to the configured platform. With
 * --simulator the onboarding is instant: the connection is verified at once
 * and what the organisation waited to send is released. The SIREN is given
 * by hand — this context never reads the clinics; a typo is caught when a
 * document is submitted (seller SIREN ≠ organisation).
 */
#[AsCommand(name: 'app:electronic-invoicing:connect', description: 'Connect an organisation (SIREN) to the e-invoicing platform')]
final class ConnectPlatformCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('siren', InputArgument::REQUIRED, 'The organisation SIREN')
            ->addOption('simulator', null, InputOption::VALUE_NONE, 'Verify the connection at once (local simulator)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io    = new SymfonyStyle($input, $output);
        $siren = $input->getArgument('siren');
        \assert(\is_string($siren));

        try {
            $this->commandBus->dispatch(new OpenPlatformConnection($siren));

            if (true === $input->getOption('simulator')) {
                $this->commandBus->dispatch(new VerifyPlatformConnection($siren));
            }
        } catch (\LogicException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Organisation %s connected%s.', $siren, true === $input->getOption('simulator') ? ' and verified' : ''));

        return Command::SUCCESS;
    }
}
