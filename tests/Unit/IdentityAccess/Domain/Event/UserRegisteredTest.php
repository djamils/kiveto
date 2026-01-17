<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Domain\Event;

use App\IdentityAccess\Domain\Event\UserRegistered;
use PHPUnit\Framework\TestCase;

final class UserRegisteredTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new UserRegistered(
            userId: '11111111-1111-1111-1111-111111111111',
            email: 'user@example.com',
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $event->aggregateId());
        self::assertSame(
            [
                'userId' => '11111111-1111-1111-1111-111111111111',
                'email'  => 'user@example.com',
            ],
            $event->payload(),
        );
        self::assertSame('identity-access.user.registered.v1', $event->name());
    }
}
