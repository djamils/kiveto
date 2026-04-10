<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\ListAllMemberships;

use App\Context\Clinic\Application\Query\Staff\ListAllMemberships\ListAllMemberships;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class ListAllMembershipsTest extends TestCase
{
    public function testConstructWithAllFilters(): void
    {
        $query = new ListAllMemberships(
            clinicId: 'clinic-uuid',
            userId: 'user-uuid',
            status: ClinicMembershipStatus::ACTIVE,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        );

        self::assertSame('clinic-uuid', $query->clinicId);
        self::assertSame('user-uuid', $query->userId);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $query->status);
        self::assertSame(ClinicMemberRole::VETERINARY, $query->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $query->engagement);
    }

    public function testConstructWithDefaults(): void
    {
        $query = new ListAllMemberships();

        self::assertNull($query->clinicId);
        self::assertNull($query->userId);
        self::assertNull($query->status);
        self::assertNull($query->role);
        self::assertNull($query->engagement);
    }
}
