<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\IdentityAccess\Pipeline;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\System\IdentityAccess\Application\Command\ActivateInvitedUser\ActivateInvitedUser;
use App\System\IdentityAccess\Application\Command\ActivateInvitedUser\ActivationResult;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\InvitedUserRegistration;
use App\System\IdentityAccess\Application\Command\RegisterInvitedUser\RegisterInvitedUser;
use App\System\IdentityAccess\Application\Command\RequestPasswordReset\RequestPasswordReset;
use App\System\IdentityAccess\Application\Command\SendInvitation\SendInvitation;
use App\System\IdentityAccess\Application\Query\GetInvitationStatuses\GetInvitationStatuses;
use App\Tests\Support\MessengerDrain;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The account e-mails through the real buses, tables and Notification:
 * sent by « Kiveto » (AC 3), carrying a working link; the reset request is
 * processed by the worker, not during the call.
 */
final class AccountEmailsPipelineTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000bc01';

    public function testAnInvitationIsSentByKivetoAndItsLinkActivatesTheAccount(): void
    {
        $registration = $this->command(new RegisterInvitedUser('hugo.pipeline@example.com'));
        self::assertInstanceOf(InvitedUserRegistration::class, $registration);

        $tokenId = $this->command(new SendInvitation($registration->userId, self::CLINIC, 'Dr Claire Lambert', 'Clinique Montmartre', 'Paris', 'Hugo', 'Vétérinaire'));
        self::assertIsString($tokenId);

        $message = $this->message('invitation:' . $tokenId);
        self::assertSame(['STAFF_INVITATION', 'QUEUED', 'Kiveto', 'no-reply@kiveto.app', 'Dr Claire Lambert vous invite à rejoindre Clinique Montmartre sur Kiveto'], [
            $message['purpose'], $message['status'], $message['sender_name'], $message['sender_email'], $message['subject'],
        ]);
        self::assertSame(['userId' => 'PENDING_VALID'], $this->invitationStatuses($registration->userId));

        self::assertSame(ActivationResult::ACTIVATED, $this->command(new ActivateInvitedUser($this->link($tokenId), 'un-mot-de-passe')));

        self::assertSame(['userId' => 'ACTIVE'], $this->invitationStatuses($registration->userId));
        self::assertSame(['default'], $this->queued('UserActivatedIntegrationEvent'), 'the activation is in the outbox');
    }

    /**
     * AC 31: the request is queued; the worker sends the e-mail of an ACTIVE account only.
     */
    public function testAResetRequestIsProcessedByTheWorker(): void
    {
        $registration = $this->command(new RegisterInvitedUser('lea.pipeline@example.com'));
        \assert($registration instanceof InvitedUserRegistration);
        $tokenId = $this->command(new SendInvitation($registration->userId, self::CLINIC, 'Dr Claire Lambert', 'Clinique Montmartre', 'Paris', '', 'ASV'));
        \assert(\is_string($tokenId));
        $this->command(new ActivateInvitedUser($this->link($tokenId), 'un-mot-de-passe'));

        // As the controller does: on the raw command bus, which routes it to `async`.
        $bus = self::getContainer()->get('messenger.bus.command');
        \assert($bus instanceof MessageBusInterface);
        $bus->dispatch(new RequestPasswordReset('lea.pipeline@example.com'));
        $bus->dispatch(new RequestPasswordReset('nobody.pipeline@example.com'));

        self::assertSame(0, $this->messagesOf('PASSWORD_RESET'), 'nothing is done during the call');
        self::assertCount(2, $this->queued('RequestPasswordReset'));

        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertSame(1, $this->messagesOf('PASSWORD_RESET'));
        $message = $this->connection()->fetchAssociative("SELECT recipient_address, JSON_UNQUOTE(JSON_EXTRACT(sender, '$.email')) AS sender FROM notification__outbound_messages WHERE purpose = 'PASSWORD_RESET'");
        self::assertSame(['recipient_address' => 'lea.pipeline@example.com', 'sender' => 'no-reply@kiveto.app'], $message);
    }

    private function command(object $command): mixed
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus->dispatch($command);
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationStatuses(string $userId): array
    {
        $bus = self::getContainer()->get(QueryBusInterface::class);
        \assert($bus instanceof QueryBusInterface);
        $statuses = $bus->ask(new GetInvitationStatuses(self::CLINIC, [$userId]));
        \assert(\is_array($statuses));

        return ['userId' => $statuses[$userId] ?? null];
    }

    /**
     * @return array<string, mixed>
     */
    private function message(string $key): array
    {
        $row = $this->connection()->fetchAssociative(
            "SELECT purpose, status, JSON_UNQUOTE(JSON_EXTRACT(sender, '$.name')) AS sender_name, JSON_UNQUOTE(JSON_EXTRACT(sender, '$.email')) AS sender_email,"
            . " JSON_UNQUOTE(JSON_EXTRACT(content, '$.subject')) AS subject, JSON_UNQUOTE(JSON_EXTRACT(content, '$.html')) AS html"
            . ' FROM notification__outbound_messages WHERE idempotency_key = ?',
            [$key],
        );
        self::assertIsArray($row);

        return $row;
    }

    /** The activation secret in the e-mail of this invitation. */
    private function link(string $tokenId): string
    {
        $html = $this->message('invitation:' . $tokenId)['html'];
        self::assertIsString($html);
        self::assertSame(1, preg_match('#http://clinic\.kiveto\.local/invitation/([A-Za-z0-9_-]{43})#', $html, $link));

        return $link[1];
    }

    private function messagesOf(string $purpose): int
    {
        $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM notification__outbound_messages WHERE purpose = ?', [$purpose]);

        return is_numeric($count) ? (int) $count : -1;
    }

    /**
     * @return list<string>
     */
    private function queued(string $needle): array
    {
        return array_map(static fn (mixed $queue): string => \is_string($queue) ? $queue : '', $this->connection()->fetchFirstColumn('SELECT queue_name FROM shared__messenger_messages WHERE body LIKE ?', ['%' . $needle . '%']));
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);

        return $connection;
    }
}
