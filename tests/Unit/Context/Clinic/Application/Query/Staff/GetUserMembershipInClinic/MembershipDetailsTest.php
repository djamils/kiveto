<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic;

use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\MembershipDetails;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class MembershipDetailsTest extends TestCase
{
    public function testProperties(): void
    {
        $validFrom = new \DateTimeImmutable('2026-01-01');

        $details = new MembershipDetails(
            membershipId: 'membership-uuid',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: $validFrom,
            validUntil: null,
            isEffectiveNow: true,
        );

        self::assertSame('membership-uuid', $details->membershipId);
        self::assertSame(ClinicMemberRole::VETERINARY, $details->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $details->engagement);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $details->status);
        self::assertSame($validFrom, $details->validFrom);
        self::assertNull($details->validUntil);
        self::assertTrue($details->isEffectiveNow);
    }
}
