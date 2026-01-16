<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain\ValueObject;

use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipEngagementTest extends TestCase
{
    public function testEnumHasEmployeeCase(): void
    {
        self::assertSame('EMPLOYEE', ClinicMembershipEngagement::EMPLOYEE->value);
    }

    public function testEnumHasContractorCase(): void
    {
        self::assertSame('CONTRACTOR', ClinicMembershipEngagement::CONTRACTOR->value);
    }

    public function testFromStringReturnsCorrectEnum(): void
    {
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, ClinicMembershipEngagement::from('EMPLOYEE'));
        self::assertSame(ClinicMembershipEngagement::CONTRACTOR, ClinicMembershipEngagement::from('CONTRACTOR'));
    }

    public function testAllCasesReturnsTwoCases(): void
    {
        $cases = ClinicMembershipEngagement::cases();

        self::assertCount(2, $cases);
        self::assertContains(ClinicMembershipEngagement::EMPLOYEE, $cases);
        self::assertContains(ClinicMembershipEngagement::CONTRACTOR, $cases);
    }
}
