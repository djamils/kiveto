<?php

declare(strict_types=1);

namespace App\Tests\Integration\System\Notification;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessageHandler;
use App\System\Notification\Application\Command\RequestNotification\AttachmentReference;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\System\Notification\Domain\Message\OutboundMessageId;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\System\Notification\Infrastructure\Messaging\DispatchFailureListener;
use App\System\Notification\Infrastructure\Messaging\DispatchRetryStrategy;
use App\System\Notification\Infrastructure\Transport\ProviderFailureClassifier;
use App\System\Notification\Infrastructure\Transport\SymfonyMailerEmailSender;
use App\System\Notification\Infrastructure\Transport\SymfonyTexterSmsSender;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Notifier\Bridge\Brevo\BrevoTransport;

/**
 * The real Brevo bridges (`brevo+api` mailer transport, `brevo` texter) on a
 * mocked HTTP client, driven by the dispatch handler: what Brevo receives,
 * and the Brevo message id stored on the message — the CI otherwise only
 * runs `null://null`.
 */
final class BrevoTransportContractTest extends KernelTestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c0b2';
    private const string PDF    = 'notification-test/contract/FA-2026-0814.pdf';
    private const string LOGO   = 'notification-test/contract/logo.png';

    /** @var list<array{string, string, array<mixed>}> */
    private array $requests = [];

    /**
     * AC 2: sender, reply-to, recipient, base64 attachment, inline logo,
     * idempotency tag; the message becomes SENT with Brevo's message id.
     */
    public function testAnEmailReachesBrevoAsTheClinic(): void
    {
        $storage = $this->storage();
        $storage->storeAt(self::PDF, '%PDF-1.7 invoice');
        $storage->storeAt(self::LOGO, 'PNG-bytes');

        try {
            $this->profile(self::LOGO);
            $id = $this->request(new RequestNotification(
                purpose: 'INVOICE_SENT',
                channel: 'EMAIL',
                recipientAddress: 't.lefebvre@orange.fr',
                recipientName: 'Thomas Lefebvre',
                clinicId: self::CLINIC,
                clientId: null,
                subjectType: 'invoice',
                subjectId: 'inv-1',
                idempotencyKey: 'invoice:inv-1:send:1',
                parameters: MessageRenderingTest::reference()['INVOICE_SENT']['EMAIL'],
                attachments: [new AttachmentReference(self::PDF, 'FA-2026-0814.pdf', 'application/pdf', 'REQUESTER')],
            ));

            $this->handler()(new DispatchOutboundMessage($id));
        } finally {
            $storage->delete(self::PDF);
            $storage->delete(self::LOGO);
        }

        self::assertCount(1, $this->requests);
        [$method, $url, $options] = $this->requests[0];
        self::assertSame(['POST', 'https://api.brevo.com/v3/smtp/email'], [$method, $url]);
        self::assertIsArray($options['headers'] ?? null);
        self::assertContains('api-key: KEY', $options['headers']);

        $payload = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '', true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload));
        self::assertSame(['email' => 'clinique-montmartre@mail.kiveto.fr', 'name' => 'Clinique Montmartre'], $payload['sender']);
        self::assertSame(['email' => 'accueil@clinique-montmartre.fr'], $payload['replyTo']);
        self::assertSame([['email' => 't.lefebvre@orange.fr', 'name' => 'Thomas Lefebvre']], $payload['to']);
        self::assertSame('Votre facture FA-2026-0814 — Clinique Montmartre', $payload['subject']);
        self::assertSame(['invoice:inv-1:send:1'], $payload['tags']);
        self::assertSame([
            ['content' => base64_encode('%PDF-1.7 invoice'), 'name' => 'FA-2026-0814.pdf'],
            ['content' => base64_encode('PNG-bytes'), 'name' => 'logo.png'],
        ], $payload['attachment']);
        self::assertIsString($payload['htmlContent']);
        self::assertStringContainsString('src="cid:logo.png"', $payload['htmlContent']);
        self::assertIsString($payload['textContent']);

        $message = $this->messages()->get(OutboundMessageId::fromString($id));
        self::assertSame(DeliveryStatus::SENT, $message->status());
        self::assertSame('<202610211230.11866@smtp-relay.mailin.fr>', $message->externalMessageId());
    }

    /**
     * The SMS goes from the clinic's sender (per message, not the DSN one)
     * with the idempotency key as tag; Brevo's numeric id is stored.
     */
    public function testAnSmsReachesBrevoFromTheClinicSender(): void
    {
        $this->profile(null);
        $id = $this->request(new RequestNotification(
            purpose: 'APPOINTMENT_CONFIRMATION',
            channel: 'SMS',
            recipientAddress: '+33612345678',
            recipientName: 'Sophie Dubois',
            clinicId: self::CLINIC,
            clientId: null,
            subjectType: 'appointment',
            subjectId: 'a-1',
            idempotencyKey: 'appointment:a-1:confirmation:1',
            parameters: MessageRenderingTest::reference()['APPOINTMENT_CONFIRMATION']['SMS'],
        ));

        $this->handler()(new DispatchOutboundMessage($id));

        [$method, $url, $options] = $this->requests[0];
        self::assertSame(['POST', 'https://api.brevo.com/v3/transactionalSMS/sms'], [$method, $url]);
        $payload = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '', true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($payload));
        self::assertSame('Montmartre', $payload['sender']);
        self::assertSame('+33612345678', $payload['recipient']);
        self::assertSame('appointment:a-1:confirmation:1', $payload['tag']);
        self::assertSame($this->messages()->get(OutboundMessageId::fromString($id))->content()?->smsText, $payload['content']);

        $message = $this->messages()->get(OutboundMessageId::fromString($id));
        self::assertSame(DeliveryStatus::SENT, $message->status());
        self::assertSame('1511882900176220', $message->externalMessageId());
    }

    public function testTheNotificationQueueRetriesWithTheProviderDelayAndRecordsTheLastFailure(): void
    {
        $strategies = self::getContainer()->get('messenger.retry_strategy_locator');
        \assert($strategies instanceof ContainerInterface);
        self::assertInstanceOf(DispatchRetryStrategy::class, $strategies->get('async_external_notification'));

        $dispatcher = self::getContainer()->get('event_dispatcher');
        \assert($dispatcher instanceof EventDispatcherInterface);
        $listeners = array_map(static fn (mixed $l): string => \is_array($l) && \is_object($l[0]) ? $l[0]::class : get_debug_type($l), $dispatcher->getListeners(WorkerMessageFailedEvent::class));
        self::assertContains(DispatchFailureListener::class, $listeners);
    }

    private function handler(): DispatchOutboundMessageHandler
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requests[] = [$method, $url, $options];

            return str_ends_with($url, '/v3/smtp/email')
                ? new MockResponse('{"messageId":"<202610211230.11866@smtp-relay.mailin.fr>"}', ['http_code' => 201])
                : new MockResponse('{"reference":"ab1cde2fgh3i4jklmno","messageId":1511882900176220}', ['http_code' => 201]);
        });
        $clock = self::getContainer()->get(ClockInterface::class);
        \assert($clock instanceof ClockInterface);
        $failures = new ProviderFailureClassifier($clock);

        return new DispatchOutboundMessageHandler(
            $this->messages(),
            new SymfonyMailerEmailSender(new BrevoApiTransport('KEY', $http), $failures),
            new SymfonyTexterSmsSender(new BrevoTransport('KEY', 'Kiveto', $http), $failures),
            $this->storage(),
            $this->bus(),
            $clock,
            new NullLogger(),
        );
    }

    private function profile(?string $logo): void
    {
        $this->bus()->dispatch(new UpsertClinicMessagingProfile(self::CLINIC, 'Clinique Montmartre', 'clinique-montmartre', 'accueil@clinique-montmartre.fr', 'Montmartre', 500, '18 rue Lepic, 75018 Paris', 'Paris 18e', '01 42 64 18 00', '#4338CA', $logo));
    }

    private function request(RequestNotification $request): string
    {
        $id = $this->bus()->dispatch($request);
        \assert(\is_string($id));

        return $id;
    }

    private function bus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }

    private function messages(): OutboundMessageRepositoryInterface
    {
        $messages = self::getContainer()->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);

        return $messages;
    }

    private function storage(): FileStorageInterface
    {
        $storage = self::getContainer()->get(FileStorageInterface::class);
        \assert($storage instanceof FileStorageInterface);

        return $storage;
    }
}
