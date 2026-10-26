<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Webhook;

use App\System\Notification\Application\Command\RecordDeliveryEvent\RecordDeliveryEvent;
use App\System\Notification\Infrastructure\Webhook\BrevoMailerRequestParser;
use App\System\Notification\Infrastructure\Webhook\BrevoSmsPayloadConverter;
use App\System\Notification\Infrastructure\Webhook\MailerEventConsumer;
use App\System\Notification\Infrastructure\Webhook\SmsEventConsumer;
use App\Tests\Unit\System\Notification\Support\BrevoPayloads;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\RemoteEvent\Event\Sms\SmsEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * Task 3.3: raw Brevo event → RecordDeliveryEvent (kind, time, reason).
 */
final class WebhookEventConsumersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function provideEmailEventsCases(): iterable
    {
        yield 'request' => ['request', 'SENT', null];
        yield 'delivered' => ['delivered', 'DELIVERED', null];
        yield 'deferred' => ['deferred', 'DEFERRED', 'Greylisted, please try again later'];
        yield 'soft bounce' => ['soft_bounce', 'DEFERRED', 'Mailbox full'];
        yield 'hard bounce' => ['hard_bounce', 'BOUNCED', '550 5.1.1 The email account that you tried to reach does not exist'];
        yield 'invalid e-mail' => ['invalid_email', 'REJECTED', 'Invalid email address'];
        yield 'blocked' => ['blocked', 'REJECTED', 'Blocked: recipient previously hard-bounced'];
        yield 'error' => ['error', 'REJECTED', 'Sending error'];
        yield 'spam' => ['spam', 'COMPLAINT', null];
        yield 'opened (ignored)' => ['opened', null, null];
    }

    #[DataProvider('provideEmailEventsCases')]
    public function testEmailEvents(string $fixture, ?string $kind, ?string $reason): void
    {
        $payload = BrevoPayloads::payload('email', $fixture);
        $event   = new BrevoMailerRequestParser(BrevoRequestGuardTest::guard())->parse(BrevoPayloads::request($payload), '');
        \assert($event instanceof RemoteEvent);
        $buses = new CapturingBuses();

        new MailerEventConsumer($buses)->consume($event);

        if (null === $kind) {
            self::assertSame([], $buses->commands);

            return;
        }

        self::assertSame([(array) new RecordDeliveryEvent('EMAIL', '<202610260942.81234567890@smtp-relay.mailin.fr>', $kind, BrevoPayloads::eventTime($payload), $reason)], self::recorded($buses));
    }

    public function testTheMailerConsumerOnlyTakesMailerEvents(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new MailerEventConsumer(new CapturingBuses())->consume(new SmsEvent('delivered', '1', []));
    }

    /**
     * @return iterable<string, array{string, ?string, ?string}>
     */
    public static function provideSmsEventsCases(): iterable
    {
        yield 'sent' => ['sent', 'SENT', null];
        yield 'accepted' => ['accepted', 'SENT', null];
        yield 'delivered' => ['delivered', 'DELIVERED', null];
        yield 'soft bounce' => ['soft_bounce', 'DEFERRED', 'Handset temporarily unreachable'];
        yield 'hard bounce' => ['hard_bounce', 'BOUNCED', 'Unknown or inactive number'];
        yield 'rejected' => ['rejected', 'REJECTED', 'Rejected by the operator'];
        yield 'error' => ['error', 'FAILED', 'Sending error'];
        yield 'replied (ignored)' => ['replied', null, null];
    }

    #[DataProvider('provideSmsEventsCases')]
    public function testSmsEvents(string $fixture, ?string $kind, ?string $reason): void
    {
        $payload = BrevoPayloads::payload('sms', $fixture);
        $buses   = new CapturingBuses();

        new SmsEventConsumer($buses)->consume(new BrevoSmsPayloadConverter()->convert($payload));

        if (null === $kind) {
            self::assertSame([], $buses->commands);

            return;
        }

        self::assertSame([(array) new RecordDeliveryEvent('SMS', '1511882900176220', $kind, BrevoPayloads::eventTime($payload), $reason)], self::recorded($buses));
    }

    public function testAnSmsEventWithoutStatusOrWithAnEmptyDescription(): void
    {
        $buses    = new CapturingBuses();
        $consumer = new SmsEventConsumer($buses);

        $consumer->consume(new RemoteEvent('x', '1', ['msg_status' => null]));
        self::assertSame([], $buses->commands);

        $consumer->consume(new RemoteEvent('failed', '1', ['msg_status' => 'blocked', 'description' => '', 'ts_event' => 1793007727]));
        self::assertSame([(array) new RecordDeliveryEvent('SMS', '1', 'REJECTED', '2026-10-26T09:42:07+00:00', null)], self::recorded($buses));
    }

    /**
     * @return list<array<mixed>>
     */
    private static function recorded(CapturingBuses $buses): array
    {
        return array_map(static fn (object $command): array => (array) $command, $buses->commands);
    }
}
