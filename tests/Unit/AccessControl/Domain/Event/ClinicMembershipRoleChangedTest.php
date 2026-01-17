<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain\Event;

use App\AccessControl\Domain\Event\ClinicMembershipRoleChanged;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipRoleChangedTest extends TestCase
{
    public function testEventConstructionAndPayload(): void
    {
        $event = new ClinicMembershipRoleChanged(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            userId: '33333333-3333-3333-3333-333333333333',
            newRole: 'CLINIC_ADMIN',
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $event->aggregateId());

        $payload = $event->payload();
        self::assertSame('11111111-1111-1111-1111-111111111111', $payload['membershipId']);
        self::assertSame('22222222-2222-2222-2222-222222222222', $payload['clinicId']);
        self::assertSame('33333333-3333-3333-3333-333333333333', $payload['userId']);
        self::assertSame('CLINIC_ADMIN', $payload['newRole']);
    }

    public function testEventName(): void
    {
        $event = new ClinicMembershipRoleChanged(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            userId: '33333333-3333-3333-3333-333333333333',
            newRole: 'VETERINARY',
        );

        self::assertSame('clinic-access.clinic-membership-role.changed.v1', $event->name());
    }
}
