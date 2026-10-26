<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Transport;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\System\Notification\Application\Exception\DeliveryRefusedException;
use App\System\Notification\Application\Port\OutgoingEmail;
use App\System\Notification\Application\Port\OutgoingSms;
use App\System\Notification\Infrastructure\Transport\ProviderFailureClassifier;
use App\System\Notification\Infrastructure\Transport\SymfonyMailerEmailSender;
use App\System\Notification\Infrastructure\Transport\SymfonyTexterSmsSender;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException as MailerTransportException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mailer\Transport\TransportInterface as MailerTransport;
use Symfony\Component\Notifier\Bridge\Brevo\BrevoTransport;
use Symfony\Component\Notifier\Exception\TransportException as NotifierTransportException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface as NotifierTransportExceptionInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\TransportInterface as TexterTransport;

/**
 * Error mapping of the two transports; the HTTP contract with Brevo itself
 * is asserted by the integration test BrevoTransportContractTest.
 */
final class SymfonyTransportSendersTest extends TestCase
{
    private ProviderFailureClassifier $failures;

    protected function setUp(): void
    {
        $this->failures = new ProviderFailureClassifier(new FrozenClock(new \DateTimeImmutable('2026-10-21T09:30:00+00:00')));
    }

    public function testAnEmailRefusedByBrevoIsRefusedForGood(): void
    {
        $sender = new SymfonyMailerEmailSender(new BrevoApiTransport('KEY', new MockHttpClient(new MockResponse('{"message":"sender not valid"}', ['http_code' => 400]))), $this->failures);

        try {
            $sender->send(self::email());
            self::fail('Refused.');
        } catch (DeliveryRefusedException $e) {
            self::assertStringContainsString('HTTP 400', $e->reason);
            self::assertStringContainsString('sender not valid', $e->reason);
        }
    }

    public function testAnEmailBrevoCannotTakeNowIsRetried(): void
    {
        $sender = new SymfonyMailerEmailSender(new BrevoApiTransport('KEY', new MockHttpClient(new MockResponse('{"message":"too many"}', ['http_code' => 429, 'response_headers' => ['Retry-After' => '60']]))), $this->failures);

        $this->expectExceptionObject(ExternalServiceUnavailable::rateLimited('Brevo', 60));

        $sender->send(self::email());
    }

    public function testAnSmtpOutageIsRetried(): void
    {
        $transport = $this->createStub(MailerTransport::class);
        $transport->method('send')->willThrowException(new MailerTransportException('Connection could not be established with host "mailer:1025".'));

        $this->expectException(ExternalServiceUnavailable::class);

        new SymfonyMailerEmailSender($transport, $this->failures)->send(self::email());
    }

    public function testAnEmailDroppedByAListenerIsRefused(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(MessageEvent::class, static fn (MessageEvent $event) => $event->reject());

        $this->expectException(DeliveryRefusedException::class);

        new SymfonyMailerEmailSender(new NullTransport($dispatcher), $this->failures)->send(self::email());
    }

    public function testAnEmailWithoutReplyToGoesWithoutIt(): void
    {
        $id = new SymfonyMailerEmailSender(new NullTransport(), $this->failures)->send(self::email(replyTo: null));

        self::assertNotSame('', $id, 'the Message-ID of a non-API transport');
    }

    public function testAnSmsRefusedByBrevoIsRefusedForGood(): void
    {
        $sender = new SymfonyTexterSmsSender(new BrevoTransport('KEY', 'Kiveto', new MockHttpClient(new MockResponse('{"message":"invalid sender"}', ['http_code' => 400]))), $this->failures);

        $this->expectException(DeliveryRefusedException::class);

        $sender->send(self::sms());
    }

    public function testAnSmsWithoutAnswerIsRetried(): void
    {
        $sender = new SymfonyTexterSmsSender(new BrevoTransport('KEY', 'Kiveto', new MockHttpClient(new MockResponse('', ['error' => 'Connection refused']))), $this->failures);

        $this->expectException(ExternalServiceUnavailable::class);

        $sender->send(self::sms());
    }

    public function testAnSmsAnsweredWithGarbageIsRetried(): void
    {
        $sender = new SymfonyTexterSmsSender(new BrevoTransport('KEY', 'Kiveto', new MockHttpClient(new MockResponse('<html>bad gateway</html>', ['http_code' => 502]))), $this->failures);

        $this->expectException(ExternalServiceUnavailable::class);

        $sender->send(self::sms());
    }

    public function testAnSmsFromATransportWithoutIdIsTracedUnderItsTag(): void
    {
        $transport = $this->createStub(TexterTransport::class);
        $transport->method('send')->willReturnCallback(static fn (SmsMessage $message): SentMessage => new SentMessage($message, 'null'));

        self::assertSame('appointment:1:reminder', new SymfonyTexterSmsSender($transport, $this->failures)->send(self::sms()));
    }

    public function testAnSmsTransportFailureWithAResponseIsClassified(): void
    {
        $response  = new MockHttpClient(new MockResponse('{}', ['http_code' => 503]))->request('POST', 'https://api.brevo.com/v3/transactionalSMS/sms');
        $transport = $this->createStub(TexterTransport::class);
        $transport->method('send')->willThrowException(new NotifierTransportException('Unable to send the SMS', $response));

        $this->expectExceptionObject(ExternalServiceUnavailable::failed('Brevo', 503));

        new SymfonyTexterSmsSender($transport, $this->failures)->send(self::sms());
    }

    public function testAnSmsTransportFailureWithoutResponseIsRetried(): void
    {
        $transport = $this->createStub(TexterTransport::class);
        $transport->method('send')->willThrowException(new class('down') extends \RuntimeException implements NotifierTransportExceptionInterface {
            public function getDebug(): string
            {
                return '';
            }
        });

        $this->expectException(ExternalServiceUnavailable::class);

        new SymfonyTexterSmsSender($transport, $this->failures)->send(self::sms());
    }

    private static function email(?string $replyTo = 'accueil@clinique-montmartre.fr'): OutgoingEmail
    {
        return new OutgoingEmail('Clinique Montmartre', 'clinique-montmartre@mail.kiveto.fr', $replyTo, 't.lefebvre@orange.fr', 'Thomas Lefebvre', 'Sujet', '<p>Bonjour</p>', 'Bonjour', 'invoice:1:send:1');
    }

    private static function sms(): OutgoingSms
    {
        return new OutgoingSms('Montmartre', '+33612345678', 'Clinique Montmartre : rappel', 'appointment:1:reminder');
    }
}
