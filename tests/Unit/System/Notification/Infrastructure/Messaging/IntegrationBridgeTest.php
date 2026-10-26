<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Infrastructure\Messaging;

use App\System\Notification\Domain\Event\OutboundMessageStatusChanged;
use App\System\Notification\Domain\Event\OutboundMessageStatusChangedIntegrationEvent;
use App\System\Notification\Infrastructure\Messaging\Consumer\OutboundMessageStatusChangedIntegrationBridge;
use App\Tests\Unit\System\Notification\Support\CapturingBuses;
use App\Tests\Unit\System\Notification\Support\Messages;
use PHPUnit\Framework\TestCase;

final class IntegrationBridgeTest extends TestCase
{
    public function testEveryStatusIsPublishedToTheOtherContexts(): void
    {
        $buses   = new CapturingBuses();
        $bridge  = new OutboundMessageStatusChangedIntegrationBridge($buses->integrationPublisher());
        $message = Messages::email();
        $message->markFailed('HTTP 400', new \DateTimeImmutable('2026-10-16 08:01:00'));

        foreach ($message->pullDomainEvents() as $event) {
            if ($event instanceof OutboundMessageStatusChanged) {
                $bridge($event);
            }
        }

        $published = $buses->eventsOf(OutboundMessageStatusChangedIntegrationEvent::class);
        self::assertCount(2, $published);
        self::assertSame(['QUEUED', 'FAILED'], [$published[0]->status, $published[1]->status]);
        self::assertSame([1, 2], [$published[0]->sequence, $published[1]->sequence]);
        self::assertSame('01960000-0000-7000-8000-000000000e01', $published[1]->id);
        self::assertSame(Messages::CLIENT, $published[1]->clientId);
        self::assertSame('Échec', $published[1]->statusLabel);
        self::assertSame('HTTP 400', $published[1]->reason);
        self::assertSame('2026-10-16T08:01:00+00:00', $published[1]->occurredAt);
    }
}
