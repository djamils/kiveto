<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Console;

use App\Context\Clinic\Application\Command\Clinic\SyncAllClinicMessagingProfiles\SyncAllClinicMessagingProfiles;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Run once after deployment (and by the fixtures): every clinic gets its
 * messaging profile and today's veterinarian count in Notification.
 */
#[AsCommand(name: 'app:clinic:sync-messaging-profiles', description: 'Push the messaging profile and veterinarian count of every clinic to Notification')]
final class SyncClinicMessagingProfilesCommand extends Command
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->commandBus->dispatch(new SyncAllClinicMessagingProfiles());
        \assert(\is_int($count));

        new SymfonyStyle($input, $output)->success(\sprintf('%d clinic messaging profile(s) synchronised.', $count));

        return Command::SUCCESS;
    }
}
