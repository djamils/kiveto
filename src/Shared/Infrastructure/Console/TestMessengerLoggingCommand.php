<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Translation\Application\Command\UpsertTranslation\UpsertTranslation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Temporary command to test Messenger logging functionality.
 * Dispatches a Translation command to verify logs appear in dev.log.
 */
#[AsCommand(
    name: 'app:test-messenger-logging',
    description: 'Test Messenger logging by dispatching a Translation command',
)]
final class TestMessengerLoggingCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Messenger Logging');

        // Dispatch a test command
        $command = new UpsertTranslation(
            scope: 'clinic',
            locale: 'fr_FR',
            domain: 'messages',
            key: 'test.messenger.logging',
            value: 'Test logging value',
            description: 'Test description',
            actorId: null, // No actorId for this test
        );

        $io->info('Dispatching UpsertTranslation command...');
        $this->commandBus->dispatch($command);

        $io->success('Command dispatched! Check var/log/dev.log for logs with channel "messenger"');
        $io->note('Run: tail -f var/log/dev.log | grep messenger');

        return Command::SUCCESS;
    }
}
