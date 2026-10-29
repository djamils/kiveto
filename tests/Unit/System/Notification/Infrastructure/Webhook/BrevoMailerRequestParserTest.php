<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Webhook;

use App\System\Notification\Infrastructure\Webhook\BrevoMailerRequestParser;
use App\Tests\Unit\System\Notification\Support\BrevoPayloads;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class BrevoMailerRequestParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRecordedEventsCases(): iterable
    {
        yield 'request' => ['request', MailerDeliveryEvent::RECEIVED];
        yield 'delivered' => ['delivered', MailerDeliveryEvent::DELIVERED];
        yield 'deferred' => ['deferred', MailerDeliveryEvent::DEFERRED];
        yield 'soft bounce' => ['soft_bounce', MailerDeliveryEvent::BOUNCE];
        yield 'hard bounce' => ['hard_bounce', MailerDeliveryEvent::BOUNCE];
        yield 'invalid e-mail' => ['invalid_email', MailerDeliveryEvent::DROPPED];
        yield 'blocked' => ['blocked', MailerDeliveryEvent::DROPPED];
        yield 'error' => ['error', MailerDeliveryEvent::DROPPED];
        yield 'opened' => ['opened', MailerEngagementEvent::OPEN];
    }

    #[DataProvider('provideRecordedEventsCases')]
    public function testRecordedEvents(string $fixture, string $name): void
    {
        $payload = BrevoPayloads::payload('email', $fixture);

        $event = self::parser()->parse(BrevoPayloads::request($payload), '');

        self::assertInstanceOf(AbstractMailerEvent::class, $event);
        self::assertSame($name, $event->getName());
        self::assertSame('<202610260942.81234567890@smtp-relay.mailin.fr>', $event->getId());
        self::assertSame($fixture, $event->getPayload()['event'], 'the raw Brevo event name is kept for the consumer');
        self::assertSame(BrevoPayloads::eventTime($payload), $event->getDate()->format(\DATE_ATOM));
    }

    public function testASpamReportIsAComplaint(): void
    {
        $event = self::parser()->parse(BrevoPayloads::request(BrevoPayloads::payload('email', 'spam')), '');

        self::assertInstanceOf(MailerEngagementEvent::class, $event);
        self::assertSame(MailerEngagementEvent::SPAM, $event->getName());
        self::assertSame('complaint', $event->getPayload()['event']);
    }

    public function testAMalformedPayloadIsRejected(): void
    {
        $payload = BrevoPayloads::payload('email', 'delivered');
        unset($payload['message-id']);

        self::assertRejected(406, BrevoPayloads::request($payload));
        self::assertRejected(406, BrevoPayloads::request(['event' => 'list_addition'] + BrevoPayloads::payload('email', 'delivered')));
        self::assertRejected(406, BrevoPayloads::request(BrevoPayloads::payload('email', 'delivered'), method: 'GET'));
    }

    public function testTheGuardRunsFirst(): void
    {
        self::assertRejected(403, BrevoPayloads::request(BrevoPayloads::payload('email', 'delivered'), ip: '203.0.113.7'));
        self::assertRejected(401, BrevoPayloads::request(BrevoPayloads::payload('email', 'delivered'), bearer: 'guess'));
    }

    public function testBrevoGetsA200(): void
    {
        self::assertSame(200, self::parser()->createSuccessfulResponse(new Request())->getStatusCode());
    }

    private static function parser(): BrevoMailerRequestParser
    {
        return new BrevoMailerRequestParser(BrevoRequestGuardTest::guard());
    }

    private static function assertRejected(int $status, Request $request): void
    {
        try {
            self::parser()->parse($request, '');
            self::fail('The call should have been rejected.');
        } catch (RejectWebhookException $e) {
            self::assertSame($status, $e->getStatusCode());
        }
    }
}
