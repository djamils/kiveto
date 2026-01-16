<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain\Event;

use App\AccessControl\Domain\Event\ClinicMembershipCreated;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipCreatedTest extends TestCase
{
    public function testEventConstructionAndPayload(): void
    {
        $event = new ClinicMembershipCreated(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            userId: '33333333-3333-3333-3333-333333333333',
            role: 'VETERINARY',
            engagement: 'EMPLOYEE',
            validFrom: '2024-01-01',
            validUntil: '2025-01-01',
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $event->aggregateId());

        $payload = $event->payload();
        self::assertSame('11111111-1111-1111-1111-111111111111', $payload['membershipId']);
        self::assertSame('22222222-2222-2222-2222-222222222222', $payload['clinicId']);
        self::assertSame('33333333-3333-3333-3333-333333333333', $payload['userId']);
        self::assertSame('VETERINARY', $payload['role']);
        self::assertSame('EMPLOYEE', $payload['engagement']);
        self::assertSame('2024-01-01', $payload['validFrom']);
        self::assertSame('2025-01-01', $payload['validUntil']);
    }

    public function testEventWithNullValidUntil(): void
    {
        $event = new ClinicMembershipCreated(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            userId: '33333333-3333-3333-3333-333333333333',
            role: 'CLINIC_ADMIN',
            engagement: 'CONTRACTOR',
            validFrom: '2024-01-01',
            validUntil: null,
        );

        $payload = $event->payload();
        self::assertNull($payload['validUntil']);
    }

    public function testEventName(): void
    {
        $event = new ClinicMembershipCreated(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            userId: '33333333-3333-3333-3333-333333333333',
            role: 'VETERINARY',
            engagement: 'EMPLOYEE',
            validFrom: '2024-01-01',
            validUntil: null,
        );

        self::assertSame('clinic-access.clinic-membership.created.v1', $event->type());
    }
}
