<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Domain\ValueObject;

use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use PHPUnit\Framework\TestCase;

final class ClinicMemberRoleTest extends TestCase
{
    public function testEnumHasClinicAdminCase(): void
    {
        self::assertSame('CLINIC_ADMIN', ClinicMemberRole::CLINIC_ADMIN->value);
    }

    public function testEnumHasVeterinaryCase(): void
    {
        self::assertSame('VETERINARY', ClinicMemberRole::VETERINARY->value);
    }

    public function testEnumHasAssistantVeterinaryCase(): void
    {
        self::assertSame('ASSISTANT_VETERINARY', ClinicMemberRole::ASSISTANT_VETERINARY->value);
    }

    public function testFromStringReturnsCorrectEnum(): void
    {
        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, ClinicMemberRole::from('CLINIC_ADMIN'));
        self::assertSame(ClinicMemberRole::VETERINARY, ClinicMemberRole::from('VETERINARY'));
        self::assertSame(ClinicMemberRole::ASSISTANT_VETERINARY, ClinicMemberRole::from('ASSISTANT_VETERINARY'));
    }

    public function testAllCasesReturnsThreeCases(): void
    {
        $cases = ClinicMemberRole::cases();

        self::assertCount(3, $cases);
        self::assertContains(ClinicMemberRole::CLINIC_ADMIN, $cases);
        self::assertContains(ClinicMemberRole::VETERINARY, $cases);
        self::assertContains(ClinicMemberRole::ASSISTANT_VETERINARY, $cases);
    }
}
