<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\Event;

use App\System\IdentityAccess\Domain\Event\UserActivated;
use App\System\IdentityAccess\Domain\Event\UserInvited;
use App\System\IdentityAccess\Domain\Event\UserPasswordChanged;
use PHPUnit\Framework\TestCase;

final class AccountLifecycleEventsTest extends TestCase
{
    public function testEventsCarryTheUser(): void
    {
        $invited   = new UserInvited('u-1', 'hugo@example.com');
        $activated = new UserActivated('u-1', '2026-10-26T10:00:00+00:00');
        $changed   = new UserPasswordChanged('u-1', '2026-10-26T11:00:00+00:00');

        self::assertSame(['identity-access.user.invited.v1', 'u-1', ['userId' => 'u-1', 'email' => 'hugo@example.com']], [$invited->name(), $invited->aggregateId(), $invited->payload()]);
        self::assertSame(['identity-access.user.activated.v1', 'u-1', ['userId' => 'u-1', 'occurredAt' => '2026-10-26T10:00:00+00:00']], [$activated->name(), $activated->aggregateId(), $activated->payload()]);
        self::assertSame(['identity-access.user-password.changed.v1', 'u-1', ['userId' => 'u-1', 'occurredAt' => '2026-10-26T11:00:00+00:00']], [$changed->name(), $changed->aggregateId(), $changed->payload()]);
    }
}
