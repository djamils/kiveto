<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\Staff\Event;

use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipCreated;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipCreatedTest extends TestCase
{
    public function testPayloadAndAggregateId(): void
    {
        $event = new ClinicMembershipCreated(
            membershipId: 'mid',
            clinicId: 'cid',
            userId: 'uid',
            role: 'VETERINARY',
            engagement: 'EMPLOYEE',
            validFrom: '2026-01-01T00:00:00+00:00',
            validUntil: null,
        );

        self::assertSame('mid', $event->aggregateId());

        $payload = $event->payload();
        self::assertSame('mid', $payload['membershipId']);
        self::assertSame('cid', $payload['clinicId']);
        self::assertSame('uid', $payload['userId']);
        self::assertSame('VETERINARY', $payload['role']);
        self::assertSame('EMPLOYEE', $payload['engagement']);
        self::assertSame('2026-01-01T00:00:00+00:00', $payload['validFrom']);
        self::assertNull($payload['validUntil']);
    }

    public function testEventName(): void
    {
        $event = new ClinicMembershipCreated('m', 'c', 'u', 'r', 'e', 'v', null);

        self::assertSame('clinic-staff.clinic-membership.created.v1', $event->name());
    }
}
