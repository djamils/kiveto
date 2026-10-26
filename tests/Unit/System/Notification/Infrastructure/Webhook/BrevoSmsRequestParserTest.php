<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Webhook;

use App\System\Notification\Infrastructure\Webhook\BrevoSmsPayloadConverter;
use App\System\Notification\Infrastructure\Webhook\BrevoSmsRequestParser;
use App\Tests\Unit\System\Notification\Support\BrevoPayloads;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class BrevoSmsRequestParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideRecordedEventsCases(): iterable
    {
        yield 'sent' => ['sent', 'sent'];
        yield 'accepted' => ['accepted', 'accepted'];
        yield 'delivered' => ['delivered', SmsEvent::DELIVERED];
        yield 'soft bounce' => ['soft_bounce', 'softbounce'];
        yield 'hard bounce' => ['hard_bounce', SmsEvent::FAILED];
        yield 'rejected' => ['rejected', SmsEvent::FAILED];
        yield 'error' => ['error', SmsEvent::FAILED];
        yield 'replied' => ['replied', 'replied'];
    }

    #[DataProvider('provideRecordedEventsCases')]
    public function testRecordedEvents(string $fixture, string $name): void
    {
        $event = self::parser()->parse(BrevoPayloads::request(BrevoPayloads::payload('sms', $fixture)), '');

        self::assertInstanceOf(SmsEvent::class, $event);
        self::assertSame($name, $event->getName());
        self::assertSame('1511882900176220', $event->getId());
        self::assertSame('33612345678', $event->getRecipientPhone());
        self::assertSame($fixture, $event->getPayload()['msg_status']);
    }

    public function testTheStatusIsNormalised(): void
    {
        $converter = new BrevoSmsPayloadConverter();

        self::assertSame(SmsEvent::FAILED, $converter->convert(['msg_status' => 'hardBounce'] + BrevoPayloads::payload('sms', 'sent'))->getName());
        self::assertSame('softbounce', $converter->convert(['msg_status' => 'Soft-Bounce'] + BrevoPayloads::payload('sms', 'sent'))->getName());
    }

    public function testAStringIdAndNoRecipientAreAccepted(): void
    {
        $payload = ['messageId' => 'abc-1'] + BrevoPayloads::payload('sms', 'delivered');
        unset($payload['to']);

        $event = new BrevoSmsPayloadConverter()->convert($payload);

        self::assertSame('abc-1', $event->getId());
        self::assertSame('', $event->getRecipientPhone());
    }

    public function testTheEventTimeComesFromTsEventElseFromTheDate(): void
    {
        $payload = BrevoPayloads::payload('sms', 'delivered');
        self::assertSame('2026-10-26T09:42:15+00:00', BrevoSmsPayloadConverter::occurredAt($payload)->format(\DATE_ATOM));
        self::assertSame('2026-10-26T09:42:15+00:00', BrevoSmsPayloadConverter::occurredAt(['ts_event' => '1793007735'])->format(\DATE_ATOM));

        unset($payload['ts_event']);
        self::assertSame('2026-10-26T10:42:07+01:00', BrevoSmsPayloadConverter::occurredAt($payload)->format(\DATE_ATOM));

        $this->expectException(ParseException::class);
        BrevoSmsPayloadConverter::occurredAt(['date' => '26/10/2026']);
    }

    public function testAMalformedPayloadIsRejected(): void
    {
        $payload = BrevoPayloads::payload('sms', 'delivered');

        self::assertRejected(406, BrevoPayloads::request(['messageId' => ''] + $payload));
        self::assertRejected(406, BrevoPayloads::request(['messageId' => null] + $payload));
        self::assertRejected(406, BrevoPayloads::request(['msg_status' => 3] + $payload));
        self::assertRejected(406, BrevoPayloads::request(['ts_event' => null, 'date' => null] + $payload));
        self::assertRejected(406, BrevoPayloads::request($payload, method: 'GET'));
        self::assertRejected(406, new Request(server: ['REMOTE_ADDR' => BrevoPayloads::BREVO_IP, 'REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer ' . BrevoPayloads::SECRET], content: '"not an object"'));
    }

    public function testTheGuardRunsFirst(): void
    {
        self::assertRejected(403, BrevoPayloads::request(BrevoPayloads::payload('sms', 'delivered'), ip: '203.0.113.7'));
    }

    public function testBrevoGetsA200(): void
    {
        self::assertSame(200, self::parser()->createSuccessfulResponse(new Request())->getStatusCode());
    }

    private static function parser(): BrevoSmsRequestParser
    {
        return new BrevoSmsRequestParser(BrevoRequestGuardTest::guard(), new BrevoSmsPayloadConverter());
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
