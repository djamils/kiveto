<?php

declare(strict_types=1);

namespace App\System\Notification\Infrastructure\Console;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Requests a « Message de test Kiveto » for a clinic, by e-mail (an address)
 * or SMS (an E.164 number): checks the whole pipeline — Mailpit or Brevo,
 * then the delivery webhook. The message is sent by Kiveto and counted in
 * the clinic's SMS usage.
 */
#[AsCommand(name: 'app:notification:send-test', description: 'Send a test e-mail or SMS for a clinic')]
final class SendTestNotificationCommand extends Command
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly UuidGeneratorInterface $uuids,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('clinicId', InputArgument::REQUIRED, 'The clinic id')
            ->addArgument('recipient', InputArgument::REQUIRED, 'An e-mail address or an E.164 mobile number (+33612345678)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $clinicId  = $input->getArgument('clinicId');
        $recipient = $input->getArgument('recipient');
        \assert(\is_string($clinicId) && \is_string($recipient));

        if (!Uuid::isValid($clinicId)) {
            $io->error(\sprintf('"%s" is not a clinic id.', $clinicId));

            return Command::FAILURE;
        }

        $testId = $this->uuids->generate();

        try {
            $messageId = $this->commandBus->dispatch(new RequestNotification(
                purpose: 'TEST',
                channel: str_contains($recipient, '@') ? 'EMAIL' : 'SMS',
                recipientAddress: $recipient,
                recipientName: '',
                clinicId: $clinicId,
                clientId: null,
                subjectType: 'test',
                subjectId: $testId,
                idempotencyKey: 'test:' . $testId,
                parameters: ['sentAtLabel' => $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('d/m/Y à H:i')],
            ));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        \assert(\is_string($messageId));
        $io->success(\sprintf('Test message %s requested for %s.', $messageId, $recipient));

        return Command::SUCCESS;
    }
}
