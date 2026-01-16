<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ListAllMemberships;

use App\AccessControl\Application\Query\ListAllMemberships\ListAllMemberships;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class ListAllMembershipsTest extends TestCase
{
    public function testQueryConstructionWithNoFilters(): void
    {
        $query = new ListAllMemberships();

        self::assertNull($query->clinicId);
        self::assertNull($query->userId);
        self::assertNull($query->status);
        self::assertNull($query->role);
        self::assertNull($query->engagement);
    }

    public function testQueryConstructionWithAllFilters(): void
    {
        $query = new ListAllMemberships(
            clinicId: '11111111-1111-1111-1111-111111111111',
            userId: '22222222-2222-2222-2222-222222222222',
            status: ClinicMembershipStatus::ACTIVE,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $query->clinicId);
        self::assertSame('22222222-2222-2222-2222-222222222222', $query->userId);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $query->status);
        self::assertSame(ClinicMemberRole::VETERINARY, $query->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $query->engagement);
    }

    public function testQueryConstructionWithPartialFilters(): void
    {
        $query = new ListAllMemberships(
            clinicId: '11111111-1111-1111-1111-111111111111',
            status: ClinicMembershipStatus::DISABLED,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $query->clinicId);
        self::assertNull($query->userId);
        self::assertSame(ClinicMembershipStatus::DISABLED, $query->status);
        self::assertNull($query->role);
        self::assertNull($query->engagement);
    }

    public function testQueryIsReadonly(): void
    {
        $query = new ListAllMemberships(
            userId: '33333333-3333-3333-3333-333333333333',
            role: ClinicMemberRole::CLINIC_ADMIN,
        );

        self::assertSame('33333333-3333-3333-3333-333333333333', $query->userId);
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $query->role);
    }
}
