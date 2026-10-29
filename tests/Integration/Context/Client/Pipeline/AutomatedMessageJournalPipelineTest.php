<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Client\Pipeline;

use App\Context\Client\Application\Query\ListClientCommunications\ClientCommunicationView;
use App\Context\Client\Application\Query\ListClientCommunications\ListClientCommunications;
use App\Context\Client\Domain\Client;
use App\Context\Client\Domain\ClientCommunication;
use App\Context\Client\Domain\Repository\ClientCommunicationRepositoryInterface;
use App\Context\Client\Domain\Repository\ClientRepositoryInterface;
use App\Context\Client\Domain\ValueObject\Civility;
use App\Context\Client\Domain\ValueObject\ClientId;
use App\Context\Client\Domain\ValueObject\ClientIdentity;
use App\Context\Client\Domain\ValueObject\ClinicId;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Context\Client\Domain\ValueObject\ContactMethod;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessage;
use App\System\Notification\Application\Command\DispatchOutboundMessage\DispatchOutboundMessageHandler;
use App\System\Notification\Application\Command\RequestNotification\RequestNotification;
use App\System\Notification\Application\Command\UpsertClinicMessagingProfile\UpsertClinicMessagingProfile;
use App\System\Notification\Domain\Message\OutboundMessageRepositoryInterface;
use App\System\Notification\Infrastructure\Messaging\DispatchFailureListener;
use App\System\Notification\Infrastructure\Transport\ProviderFailureClassifier;
use App\System\Notification\Infrastructure\Transport\SymfonyMailerEmailSender;
use App\System\Notification\Infrastructure\Transport\SymfonyTexterSmsSender;
use App\Tests\Integration\System\Notification\MessageRenderingTest;
use App\Tests\Support\MessengerDrain;
use App\Tests\Unit\System\Notification\Support\BrevoPayloads;
use App\Tests\Unit\System\Notification\Support\CapturingMessageBus;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Notifier\Bridge\Brevo\BrevoTransport;

/**
 * The full pipeline of the Testing Strategy: RequestNotification for a
 * client → dispatch through the real Brevo bridge on a mocked HTTP client →
 * Brevo's webhook POSTed to the real WebhookController → status → the
 * client's journal, each step consumed by the workers as in production
 * (AC 2, 4, 8, 41).
 */
final class AutomatedMessageJournalPipelineTest extends WebTestCase
{
    private const string CLINIC     = '01960000-0000-7000-8000-00000000c0b3';
    private const string CLIENT     = '01960000-0000-7000-8000-00000000c1e1';
    private const string MESSAGE_ID = '<202610260942.81234567890@smtp-relay.mailin.fr>';

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        $this->browser->disableReboot();

        $this->bus()->dispatch(new UpsertClinicMessagingProfile(self::CLINIC, 'Clinique Montmartre', 'clinique-montmartre', 'accueil@clinique-montmartre.fr', 'Montmartre', 500, '18 rue Lepic, 75018 Paris', 'Paris 18e', '01 42 64 18 00', '#4338CA', null));

        $clients = self::getContainer()->get(ClientRepositoryInterface::class);
        \assert($clients instanceof ClientRepositoryInterface);
        $clients->save(Client::create(
            ClientId::fromString(self::CLIENT),
            ClinicId::fromString(self::CLINIC),
            new ClientIdentity('Sophie', 'Dubois', Civility::MADAM),
            [ContactMethod::email(EmailAddress::fromString('sophie.dubois@example.fr'), ContactLabel::HOME, true)],
            new \DateTimeImmutable('2026-01-12 10:00:00'),
        ));
    }

    public function testAnEmailToAClientIsJournaledThenFollowedUntilDelivered(): void
    {
        $id = $this->request('appointment:a-1:confirmation:1');
        MessengerDrain::drain(self::getContainer(), ['async']);

        $entry = $this->entry($id);
        self::assertTrue($entry->isAutomated());
        self::assertSame('Envoi automatique', $entry->authorName());
        self::assertSame('Rendez-vous confirmé — Milo, jeudi 22 octobre à 9h30', $entry->subject());
        self::assertSame('En cours', $entry->deliveryStatus()?->label());

        // AC 2: handed to Brevo as the clinic, SENT with Brevo's id, « Envoyé ».
        $requests = [];
        $this->dispatcher(new MockResponse('{"messageId":"' . self::MESSAGE_ID . '"}', ['http_code' => 201]), $requests)(new DispatchOutboundMessage($id));
        self::assertCount(1, $requests);
        self::assertStringContainsString('"sender":{"email":"clinique-montmartre@mail.kiveto.app","name":"Clinique Montmartre"}', $requests[0]);
        self::assertStringContainsString('"replyTo":{"email":"accueil@clinique-montmartre.fr"}', $requests[0]);

        MessengerDrain::drain(self::getContainer(), ['async']);
        self::assertSame('Envoyé', $this->entry($id)->deliveryStatus()?->label());

        // AC 8: Brevo's « delivered » webhook through the real controller.
        $this->browser->request('POST', 'http://hooks.kiveto.app/webhook/brevo_mailer?token=change-me', server: [
            'REMOTE_ADDR'  => BrevoPayloads::BREVO_IP,
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(BrevoPayloads::payload('email', 'delivered'), \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);

        MessengerDrain::drain(self::getContainer(), ['async']);

        // AC 41: one automated entry, its current status readable without colour.
        $entry = $this->entry($id);
        self::assertSame('Délivré', $entry->deliveryStatus()?->label());
        self::assertSame(1, $this->entriesOfTheClient());

        $journal = self::getContainer()->get(QueryBusInterface::class);
        \assert($journal instanceof QueryBusInterface);
        $views = $journal->ask(new ListClientCommunications(self::CLINIC, self::CLIENT));
        self::assertIsArray($views);
        self::assertCount(1, $views);
        self::assertInstanceOf(ClientCommunicationView::class, $views[0]);
        self::assertTrue($views[0]->automated);
        self::assertSame('Délivré', $views[0]->deliveryStatusLabel);
    }

    /**
     * AC 4: a dispatch that fails for the last time is recorded FAILED by
     * DispatchFailureListener, and the journal shows « Échec ».
     */
    public function testAnEmailWhoseRetriesAreExhaustedShowsAFailure(): void
    {
        $id = $this->request('appointment:a-2:confirmation:1');

        $requests = [];
        $retried  = null;
        try {
            $this->dispatcher(new MockResponse('{"message":"down"}', ['http_code' => 503]), $requests)(new DispatchOutboundMessage($id));
        } catch (ExternalServiceUnavailable $unavailable) {
            $retried = $unavailable;
        }
        self::assertNotNull($retried, 'a 5xx answer is rethrown for a Messenger retry');

        $listener = self::getContainer()->get(DispatchFailureListener::class);
        \assert($listener instanceof DispatchFailureListener);
        $listener(new WorkerMessageFailedEvent(new Envelope(new DispatchOutboundMessage($id)), 'async_external_notification', $retried));

        MessengerDrain::drain(self::getContainer(), ['async']);

        self::assertSame('Échec', $this->entry($id)->deliveryStatus()?->label());
    }

    private function request(string $key): string
    {
        $id = $this->bus()->dispatch(new RequestNotification(
            purpose: 'APPOINTMENT_CONFIRMATION',
            channel: 'EMAIL',
            recipientAddress: 'sophie.dubois@example.fr',
            recipientName: 'Sophie Dubois',
            clinicId: self::CLINIC,
            clientId: self::CLIENT,
            subjectType: 'appointment',
            subjectId: 'a-1',
            idempotencyKey: $key,
            parameters: MessageRenderingTest::reference()['APPOINTMENT_CONFIRMATION']['EMAIL'],
        ));
        \assert(\is_string($id));

        return $id;
    }

    /**
     * The dispatch handler on the real Brevo bridges, Brevo answering $answer.
     *
     * @param list<string> $requests the bodies Brevo received
     */
    private function dispatcher(MockResponse $answer, array &$requests): DispatchOutboundMessageHandler
    {
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use ($answer, &$requests): MockResponse {
            $requests[] = \is_string($options['body'] ?? null) ? $options['body'] : '';

            return $answer;
        });

        $container = self::getContainer();
        $clock     = $container->get(ClockInterface::class);
        \assert($clock instanceof ClockInterface);
        $messages = $container->get('test.' . OutboundMessageRepositoryInterface::class);
        \assert($messages instanceof OutboundMessageRepositoryInterface);
        $storage = $container->get(FileStorageInterface::class);
        \assert($storage instanceof FileStorageInterface);
        $failures = new ProviderFailureClassifier($clock, new NullLogger());

        return new DispatchOutboundMessageHandler(
            $messages,
            new SymfonyMailerEmailSender(new BrevoApiTransport('KEY', $http), $failures),
            new SymfonyTexterSmsSender(new BrevoTransport('KEY', 'Kiveto', $http), $failures),
            $storage,
            $this->bus(),
            new CapturingMessageBus(),
            $clock,
            new NullLogger(),
        );
    }

    private function entry(string $messageId): ClientCommunication
    {
        $entry = $this->journal()->findByNotificationMessageId($messageId);
        self::assertNotNull($entry, 'the message is in the client journal');
        self::assertSame(self::CLIENT, $entry->clientId()->toString());

        return $entry;
    }

    private function entriesOfTheClient(): int
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        \assert($connection instanceof Connection);

        $count = $connection->fetchOne('SELECT COUNT(*) FROM client__client_communications WHERE client_id = UUID_TO_BIN(?)', [self::CLIENT]);
        \assert(is_numeric($count));

        return (int) $count;
    }

    private function journal(): ClientCommunicationRepositoryInterface
    {
        $journal = self::getContainer()->get(ClientCommunicationRepositoryInterface::class);
        \assert($journal instanceof ClientCommunicationRepositoryInterface);

        return $journal;
    }

    private function bus(): CommandBusInterface
    {
        $bus = self::getContainer()->get(CommandBusInterface::class);
        \assert($bus instanceof CommandBusInterface);

        return $bus;
    }
}
