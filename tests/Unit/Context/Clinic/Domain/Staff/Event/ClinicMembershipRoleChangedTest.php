<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\Event;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipRoleChanged;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipRoleChangedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicMembershipRoleChanged('mid', 'cid', 'uid', 'MANAGER');

        self::assertSame('mid', $event->aggregateId());

        $payload = $event->payload();
        self::assertSame('mid', $payload['membershipId']);
        self::assertSame('cid', $payload['clinicId']);
        self::assertSame('uid', $payload['userId']);
        self::assertSame('MANAGER', $payload['newRole']);
    }
}
