<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ListAllMemberships;

use App\AccessControl\Application\Port\MembershipAdminRepositoryInterface;
use App\AccessControl\Application\Query\ListAllMemberships\ListAllMemberships;
use App\AccessControl\Application\Query\ListAllMemberships\ListAllMembershipsHandler;
use App\AccessControl\Application\Query\ListAllMemberships\MembershipCollection;
use App\AccessControl\Application\Query\ListAllMemberships\MembershipListItem;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class ListAllMembershipsHandlerTest extends TestCase
{
    public function testHandlerReturnsEmptyCollection(): void
    {
        $emptyCollection = new MembershipCollection(memberships: [], total: 0);

        $repository = $this->createStub(MembershipAdminRepositoryInterface::class);
        $repository->method('listAll')->willReturn($emptyCollection);

        $handler = new ListAllMembershipsHandler($repository);
        $query   = new ListAllMemberships();

        $result = ($handler)($query);

        self::assertCount(0, $result->memberships);
        self::assertSame(0, $result->total);
    }

    public function testHandlerReturnsMembershipCollection(): void
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

        $collection = new MembershipCollection(memberships: [$item1], total: 1);

        $repository = $this->createStub(MembershipAdminRepositoryInterface::class);
        $repository->method('listAll')->willReturn($collection);

        $handler = new ListAllMembershipsHandler($repository);
        $query   = new ListAllMemberships();

        $result = ($handler)($query);

        self::assertCount(1, $result->memberships);
        self::assertSame(1, $result->total);
        self::assertSame($item1, $result->memberships[0]);
    }

    public function testHandlerPassesFiltersToRepository(): void
    {
        $emptyCollection = new MembershipCollection(memberships: [], total: 0);

        $repository = $this->createMock(MembershipAdminRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('listAll')
            ->with(
                clinicId: '11111111-1111-1111-1111-111111111111',
                userId: '22222222-2222-2222-2222-222222222222',
                status: ClinicMembershipStatus::ACTIVE,
                role: ClinicMemberRole::VETERINARY,
                engagement: ClinicMembershipEngagement::EMPLOYEE,
            )
            ->willReturn($emptyCollection)
        ;

        $handler = new ListAllMembershipsHandler($repository);
        $query   = new ListAllMemberships(
            clinicId: '11111111-1111-1111-1111-111111111111',
            userId: '22222222-2222-2222-2222-222222222222',
            status: ClinicMembershipStatus::ACTIVE,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        );

        ($handler)($query);
    }

    public function testHandlerPassesNullFiltersToRepository(): void
    {
        $emptyCollection = new MembershipCollection(memberships: [], total: 0);

        $repository = $this->createMock(MembershipAdminRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('listAll')
            ->with(
                clinicId: null,
                userId: null,
                status: null,
                role: null,
                engagement: null,
            )
            ->willReturn($emptyCollection)
        ;

        $handler = new ListAllMembershipsHandler($repository);
        $query   = new ListAllMemberships();

        ($handler)($query);
    }
}
