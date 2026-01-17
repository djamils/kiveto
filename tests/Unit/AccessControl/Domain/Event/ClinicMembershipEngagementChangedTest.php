<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain\Event;

use App\AccessControl\Domain\Event\ClinicMembershipEngagementChanged;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipEngagementChangedTest extends TestCase
{
    public function testEventConstructionAndPayload(): void
    {
        $event = new ClinicMembershipEngagementChanged(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            userId: '33333333-3333-3333-3333-333333333333',
            newEngagement: 'CONTRACTOR',
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $event->aggregateId());

        $payload = $event->payload();
        self::assertSame('11111111-1111-1111-1111-111111111111', $payload['membershipId']);
        self::assertSame('22222222-2222-2222-2222-222222222222', $payload['clinicId']);
        self::assertSame('33333333-3333-3333-3333-333333333333', $payload['userId']);
        self::assertSame('CONTRACTOR', $payload['newEngagement']);
    }

    public function testEventName(): void
    {
        $event = new ClinicMembershipEngagementChanged(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            userId: '33333333-3333-3333-3333-333333333333',
            newEngagement: 'EMPLOYEE',
        );

        self::assertSame('clinic-access.clinic-membership-engagement.changed.v1', $event->name());
    }
}
