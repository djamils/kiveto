<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ResolveActiveClinic;

use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\AccessControl\Application\Query\ResolveActiveClinic\ActiveClinicResult;
use App\AccessControl\Application\Query\ResolveActiveClinic\ActiveClinicResultType;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class ActiveClinicResultTest extends TestCase
{
    public function testNoneReturnsNoneTypeWithEmptyData(): void
    {
        $result = ActiveClinicResult::none();

        self::assertSame(ActiveClinicResultType::NONE, $result->type);
        self::assertNull($result->clinic);
        self::assertCount(0, $result->clinics);
    }

    public function testSingleReturnsSingleTypeWithClinic(): void
    {
        $clinic = new AccessibleClinic(
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Test Clinic',
            clinicSlug: 'test-clinic',
            clinicStatus: 'ACTIVE',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
        );

        $result = ActiveClinicResult::single($clinic);

        self::assertSame(ActiveClinicResultType::SINGLE, $result->type);
        self::assertSame($clinic, $result->clinic);
        self::assertCount(1, $result->clinics);
        self::assertSame($clinic, $result->clinics[0]);
    }

    public function testMultipleReturnsMultipleTypeWithClinics(): void
    {
        $clinic1 = new AccessibleClinic(
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Clinic 1',
            clinicSlug: 'clinic-1',
            clinicStatus: 'ACTIVE',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
        );

        $clinic2 = new AccessibleClinic(
            clinicId: '44444444-4444-4444-4444-444444444444',
            clinicName: 'Clinic 2',
            clinicSlug: 'clinic-2',
            clinicStatus: 'ACTIVE',
            memberRole: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
        );

        $result = ActiveClinicResult::multiple([$clinic1, $clinic2]);

        self::assertSame(ActiveClinicResultType::MULTIPLE, $result->type);
        self::assertNull($result->clinic);
        self::assertCount(2, $result->clinics);
        self::assertSame($clinic1, $result->clinics[0]);
        self::assertSame($clinic2, $result->clinics[1]);
    }

    public function testMultipleWithEmptyArrayIsValid(): void
    {
        $result = ActiveClinicResult::multiple([]);

        self::assertSame(ActiveClinicResultType::MULTIPLE, $result->type);
        self::assertNull($result->clinic);
        self::assertCount(0, $result->clinics);
    }
}
