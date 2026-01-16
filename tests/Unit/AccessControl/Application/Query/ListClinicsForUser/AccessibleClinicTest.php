<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ListClinicsForUser;

use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class AccessibleClinicTest extends TestCase
{
    public function testDtoConstructionWithAllProperties(): void
    {
        $validFrom  = new \DateTimeImmutable('2024-01-01');
        $validUntil = new \DateTimeImmutable('2025-01-01');

        $dto = new AccessibleClinic(
            clinicId: '11111111-1111-1111-1111-111111111111',
            clinicName: 'Test Clinic',
            clinicSlug: 'test-clinic',
            clinicStatus: 'ACTIVE',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: $validFrom,
            validUntil: $validUntil,
        );

        self::assertSame('11111111-1111-1111-1111-111111111111', $dto->clinicId);
        self::assertSame('Test Clinic', $dto->clinicName);
        self::assertSame('test-clinic', $dto->clinicSlug);
        self::assertSame('ACTIVE', $dto->clinicStatus);
        self::assertSame(ClinicMemberRole::VETERINARY, $dto->memberRole);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $dto->engagement);
        self::assertSame($validFrom, $dto->validFrom);
        self::assertSame($validUntil, $dto->validUntil);
    }

    public function testDtoWithNullValidUntil(): void
    {
        $dto = new AccessibleClinic(
            clinicId: '11111111-1111-1111-1111-111111111111',
            clinicName: 'Test Clinic',
            clinicSlug: 'test-clinic',
            clinicStatus: 'ACTIVE',
            memberRole: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
        );

        self::assertNull($dto->validUntil);
    }

    public function testDtoIsReadonly(): void
    {
        $dto = new AccessibleClinic(
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Another Clinic',
            clinicSlug: 'another-clinic',
            clinicStatus: 'SUSPENDED',
            memberRole: ClinicMemberRole::ASSISTANT_VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
        );

        self::assertSame('22222222-2222-2222-2222-222222222222', $dto->clinicId);
        self::assertSame('Another Clinic', $dto->clinicName);
    }
}
