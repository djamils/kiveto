<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Messaging;

use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\System\IdentityAccess\Domain\Event\UserActivated;
use App\System\IdentityAccess\Domain\Event\UserActivatedIntegrationEvent;
use App\System\IdentityAccess\Infrastructure\Messaging\Consumer\UserActivatedIntegrationBridge;
use PHPUnit\Framework\TestCase;

final class UserActivatedIntegrationBridgeTest extends TestCase
{
    public function testTheActivationLeavesThroughTheOutbox(): void
    {
        $published = [];
        $bus       = $this->createMock(IntegrationEventBusInterface::class);
        $bus->expects(self::once())->method('publish')->willReturnCallback(static function (array $stamps, object ...$events) use (&$published): void {
            $published = $events;
        });

        new UserActivatedIntegrationBridge(new IntegrationEventPublisher($bus))(new UserActivated('u-1', '2026-10-26T10:00:00+00:00'));

        self::assertCount(1, $published);
        self::assertInstanceOf(UserActivatedIntegrationEvent::class, $published[0]);
        self::assertSame(['userId' => 'u-1', 'occurredAt' => '2026-10-26T10:00:00+00:00'], $published[0]->payload());
    }
}
