<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\InviteConsoleMember\ConsoleInvitation;
use App\System\IdentityAccess\Application\Command\InviteConsoleMember\InviteConsoleMember;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The first administrator of a new deployment (and any member, without the
 * console): prints the 7-day link to choose the password; the second
 * factor follows at the first sign-in.
 */
#[AsCommand(name: 'app:console:invite', description: 'Invite a member of the Kiveto team to the console and print their link.')]
final class InviteConsoleMemberCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Their e-mail address')
            ->addArgument('name', InputArgument::REQUIRED, 'Their name, as the console shows it')
            ->addOption('role', null, InputOption::VALUE_REQUIRED, 'admin or support', 'admin')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $name  = $input->getArgument('name');
        $role  = $input->getOption('role');

        try {
            $invitation = $this->commandBus->dispatch(new InviteConsoleMember(
                \is_string($email) ? $email : '',
                \is_string($name) ? $name : '',
                \is_string($role) ? $role : '',
            ));
        } catch (\DomainException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        \assert($invitation instanceof ConsoleInvitation);
        $output->writeln(\sprintf('<info>%s is invited.</info> Their link, valid until %s UTC:', $invitation->email, $invitation->expiresAt->format('Y-m-d H:i')));
        $output->writeln($invitation->url);

        return Command::SUCCESS;
    }
}
