<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\Event;

use App\System\IdentityAccess\Domain\Event\UserActivatedIntegrationEvent;
use PHPUnit\Framework\TestCase;

final class UserActivatedIntegrationEventTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new UserActivatedIntegrationEvent('11111111-1111-1111-1111-111111111111', '2026-10-26T10:00:00+00:00');

        self::assertSame('11111111-1111-1111-1111-111111111111', $event->aggregateId());
        self::assertSame([
            'userId'     => '11111111-1111-1111-1111-111111111111',
            'occurredAt' => '2026-10-26T10:00:00+00:00',
        ], $event->payload());
    }
}
