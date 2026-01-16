<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ListAllMemberships;

use App\AccessControl\Application\Query\ListAllMemberships\MembershipCollection;
use App\AccessControl\Application\Query\ListAllMemberships\MembershipListItem;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class MembershipCollectionTest extends TestCase
{
    public function testCollectionConstructionWithMemberships(): void
    {
        $item1 = new MembershipListItem(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Clinic 1',
            userId: '33333333-3333-3333-3333-333333333333',
            userEmail: 'user1@example.com',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2023-12-01'),
        );

        $item2 = new MembershipListItem(
            membershipId: '44444444-4444-4444-4444-444444444444',
            clinicId: '55555555-5555-5555-5555-555555555555',
            clinicName: 'Clinic 2',
            userId: '66666666-6666-6666-6666-666666666666',
            userEmail: 'user2@example.com',
            role: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2023-12-01'),
        );

        $collection = new MembershipCollection(
            memberships: [$item1, $item2],
            total: 2,
        );

        self::assertCount(2, $collection->memberships);
        self::assertSame($item1, $collection->memberships[0]);
        self::assertSame($item2, $collection->memberships[1]);
        self::assertSame(2, $collection->total);
    }

    public function testEmptyCollection(): void
    {
        $collection = new MembershipCollection(
            memberships: [],
            total: 0,
        );

        self::assertCount(0, $collection->memberships);
        self::assertSame(0, $collection->total);
    }

    public function testCollectionIsReadonly(): void
    {
        $item = new MembershipListItem(
            membershipId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Test Clinic',
            userId: '33333333-3333-3333-3333-333333333333',
            userEmail: 'test@example.com',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2023-12-01'),
        );

        $collection = new MembershipCollection(
            memberships: [$item],
            total: 1,
        );

        self::assertSame(1, $collection->total);
    }
}
