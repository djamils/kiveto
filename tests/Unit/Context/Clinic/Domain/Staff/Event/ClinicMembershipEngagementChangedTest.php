<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\Event;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipEngagementChanged;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipEngagementChangedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicMembershipEngagementChanged('mid', 'cid', 'uid', 'CONTRACTOR');

        self::assertSame('mid', $event->aggregateId());

        $payload = $event->payload();
        self::assertSame('mid', $payload['membershipId']);
        self::assertSame('cid', $payload['clinicId']);
        self::assertSame('uid', $payload['userId']);
        self::assertSame('CONTRACTOR', $payload['newEngagement']);
    }

    public function testEventName(): void
    {
        $event = new ClinicMembershipEngagementChanged('m', 'c', 'u', 'e');

        self::assertSame('clinic-staff.clinic-membership-engagement.changed.v1', $event->name());
    }
}
