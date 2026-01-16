<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\GetUserMembershipInClinic;

use App\AccessControl\Application\Query\GetUserMembershipInClinic\MembershipDetails;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use PHPUnit\Framework\TestCase;

final class MembershipDetailsTest extends TestCase
{
    public function testDtoConstructionWithAllProperties(): void
    {
        $validFrom  = new \DateTimeImmutable('2024-01-01');
        $validUntil = new \DateTimeImmutable('2025-01-01');

        $dto = new MembershipDetails(
            membershipId: '11111111-1111-1111-1111-111111111111',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: $validFrom,
            validUntil: $validUntil,
            isEffectiveNow: true,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $dto->membershipId);
        self::assertSame(ClinicMemberRole::VETERINARY, $dto->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $dto->engagement);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $dto->status);
        self::assertSame($validFrom, $dto->validFrom);
        self::assertSame($validUntil, $dto->validUntil);
        self::assertTrue($dto->isEffectiveNow);
    }

    public function testDtoWithNullValidUntil(): void
    {
        $dto = new MembershipDetails(
            membershipId: '11111111-1111-1111-1111-111111111111',
            role: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
            isEffectiveNow: true,
        );

        self::assertNull($dto->validUntil);
    }

    public function testDtoWithIsEffectiveNowFalse(): void
    {
        $dto = new MembershipDetails(
            membershipId: '11111111-1111-1111-1111-111111111111',
            role: ClinicMemberRole::ASSISTANT_VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::DISABLED,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: new \DateTimeImmutable('2025-01-01'),
            isEffectiveNow: false,
        );

        self::assertFalse($dto->isEffectiveNow);
        self::assertSame(ClinicMembershipStatus::DISABLED, $dto->status);
    }

    public function testDtoIsReadonly(): void
    {
        $dto = new MembershipDetails(
            membershipId: '22222222-2222-2222-2222-222222222222',
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
            isEffectiveNow: true,
        );

        self::assertSame('22222222-2222-2222-2222-222222222222', $dto->membershipId);
    }
}
