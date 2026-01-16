<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain\ValueObject;

use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class ClinicMembershipStatusTest extends TestCase
{
    public function testEnumHasActiveCase(): void
    {
        self::assertSame('ACTIVE', ClinicMembershipStatus::ACTIVE->value);
    }

    public function testEnumHasDisabledCase(): void
    {
        self::assertSame('DISABLED', ClinicMembershipStatus::DISABLED->value);
    }

    public function testFromStringReturnsCorrectEnum(): void
    {
        self::assertSame(ClinicMembershipStatus::ACTIVE, ClinicMembershipStatus::from('ACTIVE'));
        self::assertSame(ClinicMembershipStatus::DISABLED, ClinicMembershipStatus::from('DISABLED'));
    }

    public function testAllCasesReturnsTwoCases(): void
    {
        $cases = ClinicMembershipStatus::cases();

        self::assertCount(2, $cases);
        self::assertContains(ClinicMembershipStatus::ACTIVE, $cases);
        self::assertContains(ClinicMembershipStatus::DISABLED, $cases);
    }
}
