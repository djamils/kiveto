<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Domain;

use App\System\Notification\Domain\Delivery\DeliveryEvent;
use App\System\Notification\Domain\Message\DeliveryStatus;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\TestCase;

final class DeliveryEventTest extends TestCase
{
    public function testAnEventIsAStatusOrAComplaint(): void
    {
        $at = new \DateTimeImmutable('2026-10-21T09:31:00+00:00');

        $bounced = DeliveryEvent::ofKind('BOUNCED', $at, 'mailbox full');
        self::assertSame([DeliveryStatus::BOUNCED, 'BOUNCED', 'mailbox full'], [$bounced->status, $bounced->kind(), $bounced->reason]);

        $complaint = DeliveryEvent::ofKind(DeliveryEvent::COMPLAINT, $at, 'ignored');
        self::assertSame([null, 'COMPLAINT', null], [$complaint->status, $complaint->kind(), $complaint->reason]);
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DeliveryEvent::ofKind('OPENED', new \DateTimeImmutable());
    }

    public function testQueuedIsNotAProviderStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DeliveryEvent::status(DeliveryStatus::QUEUED, new \DateTimeImmutable());
    }

    public function testAProviderEventGoesThroughThePrecedence(): void
    {
        $message = Messages::sms();
        $message->markSent('brevo-1', new \DateTimeImmutable('2026-10-21T09:31:00+00:00'));

        self::assertTrue($message->recordProviderEvent(DeliveryEvent::status(DeliveryStatus::DELIVERED, new \DateTimeImmutable('2026-10-21T09:32:00+00:00'))));
        self::assertFalse($message->recordProviderEvent(DeliveryEvent::status(DeliveryStatus::DEFERRED, new \DateTimeImmutable('2026-10-21T09:33:00+00:00'))));
        self::assertTrue($message->recordProviderEvent(DeliveryEvent::complaint(new \DateTimeImmutable('2026-10-21T09:34:00+00:00'))));
        self::assertFalse($message->recordProviderEvent(DeliveryEvent::complaint(new \DateTimeImmutable('2026-10-21T09:35:00+00:00'))));
        self::assertSame('provider', $message->history()[2]->source);
    }
}
