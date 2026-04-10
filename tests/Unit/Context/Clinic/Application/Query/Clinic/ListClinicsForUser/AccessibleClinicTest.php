<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\ListClinicsForUser;

use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class AccessibleClinicTest extends TestCase
{
    public function testProperties(): void
    {
        $validFrom = new \DateTimeImmutable('2026-01-01');

        $clinic = new AccessibleClinic(
            clinicId: 'clinic-uuid',
            clinicName: 'Paris',
            clinicSlug: 'paris',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: $validFrom,
            validUntil: null,
            isDefault: false,
        );

        self::assertSame('clinic-uuid', $clinic->clinicId);
        self::assertSame('Paris', $clinic->clinicName);
        self::assertSame('paris', $clinic->clinicSlug);
        self::assertSame('active', $clinic->clinicStatus);
        self::assertSame(ClinicMemberRole::VETERINARY, $clinic->memberRole);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $clinic->engagement);
        self::assertSame($validFrom, $clinic->validFrom);
        self::assertNull($clinic->validUntil);
    }
}
