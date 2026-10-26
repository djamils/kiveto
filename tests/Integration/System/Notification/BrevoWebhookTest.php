<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\OutboundMessage;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\Tests\Support\MessengerDrain;
use App\Tests\Unit\System\Notification\Support\BrevoPayloads;
use App\Tests\Unit\System\Notification\Support\Messages;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * D12, AC 8 / 11 / 11b: Brevo posts to the real WebhookController on the
 * public hooks host, without any login; the parsed event is queued on
 * `async`, the worker records it, and the status change reaches the outbox.
 */
final class BrevoWebhookTest extends WebTestCase
{
    private const string MESSAGE_ID = '<202610260942.81234567890@smtp-relay.mailin.fr>';
    private const string TOKEN      = 'change-me';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();
    }

    public function testADeliveredEmailEventIsRecordedByTheWorker(): void
    {
        $message = Messages::email();
        $message->markSent(self::MESSAGE_ID, new \DateTimeImmutable('2026-10-26 09:42:00'));
        $this->messages()->save($message);

        $this->post('hooks.kiveto.fr', '/webhook/brevo_mailer?token=' . self::TOKEN, BrevoPayloads::payload('email', 'delivered'));

        self::assertResponseStatusCodeSame(200);
        self::assertSame(DeliveryStatus::SENT, $this->message($message)->status(), 'consumed asynchronously, not during the call');
        self::assertSame(['default'], $this->queued('ConsumeRemoteEventMessage'));

        MessengerDrain::drain(self::getContainer(), ['async'], 0);

        self::assertSame(DeliveryStatus::DELIVERED, $this->message($message)->status());
        self::assertSame(['default'], $this->queued($message->id()->toString()), 'the status change of a client message is in the outbox');
    }

    public function testTheBearerHeaderWorksOnAnyHostWithoutLogin(): void
    {
        $this->post('clinic.kiveto.local', '/webhook/brevo_mailer', BrevoPayloads::payload('email', 'request'), ['HTTP_AUTHORIZATION' => 'Bearer ' . self::TOKEN]);

        self::assertResponseStatusCodeSame(200);
    }

    public function testAWrongTokenOrAForeignIpChangesNothing(): void
    {
        $this->post('hooks.kiveto.fr', '/webhook/brevo_mailer?token=guess', BrevoPayloads::payload('email', 'delivered'));
        self::assertResponseStatusCodeSame(401);

        $this->post('hooks.kiveto.fr', '/webhook/brevo_sms?token=' . self::TOKEN, BrevoPayloads::payload('sms', 'delivered'), ['REMOTE_ADDR' => '203.0.113.7']);
        self::assertResponseStatusCodeSame(403);

        self::assertSame([], $this->queued('ConsumeRemoteEventMessage'));
    }

    public function testAnSmsEventForAnUnknownIdIsKeptForLater(): void
    {
        $this->post('hooks.kiveto.fr', '/webhook/brevo_sms?token=' . self::TOKEN, BrevoPayloads::payload('sms', 'delivered'));
        self::assertResponseStatusCodeSame(200);

        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertSame(1, $this->connection()->fetchOne("SELECT COUNT(*) FROM notification__unmatched_delivery_events WHERE external_id = '1511882900176220'"));
    }

    /**
     * @param array<mixed>          $payload
     * @param array<string, string> $server
     */
    private function post(string $host, string $uri, array $payload, array $server = []): void
    {
        $this->browser->request('POST', 'http://' . $host . $uri, server: $server + [
            'REMOTE_ADDR'  => BrevoPayloads::BREVO_IP,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode($payload, \JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<string> queue names of the transport rows whose body mentions $needle
     */
    private function queued(string $needle): array
    {
        return array_map(static fn (mixed $queue): string => \is_string($queue) ? $queue : '', $this->connection()->fetchFirstColumn('SELECT queue_name FROM shared__messenger_messages WHERE body LIKE ?', ['%' . $needle . '%']));
    }

    private function message(OutboundMessage $message): OutboundMessage
    {
        return $this->messages()->get(OutboundMessageId::fromString($message->id()->toString()));
    }

    private function messages(): OutboundMessageRepositoryInterface
    {
        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);

        return $messages;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        return $connection;
    }
}
