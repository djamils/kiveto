<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\Event;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipValidityChanged;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipValidityChangedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicMembershipValidityChanged(
            membershipId: 'mid',
            clinicId: 'cid',
            userId: 'uid',
            validFrom: '2026-01-01T00:00:00+00:00',
            validUntil: '2026-12-31T23:59:59+00:00',
        );

        self::assertSame('mid', $event->aggregateId());

        $payload = $event->payload();
        self::assertSame('mid', $payload['membershipId']);
        self::assertSame('cid', $payload['clinicId']);
        self::assertSame('uid', $payload['userId']);
        self::assertSame('2026-01-01T00:00:00+00:00', $payload['validFrom']);
        self::assertSame('2026-12-31T23:59:59+00:00', $payload['validUntil']);
    }

    public function testPayloadWithNullValidUntil(): void
    {
        $event = new ClinicMembershipValidityChanged('m', 'c', 'u', '2026-01-01', null);

        self::assertNull($event->payload()['validUntil']);
    }

    public function testEventName(): void
    {
        $event = new ClinicMembershipValidityChanged('m', 'c', 'u', 'v', null);

        self::assertSame('clinic-staff.clinic-membership-validity.changed.v1', $event->name());
    }
}
