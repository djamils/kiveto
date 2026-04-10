<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\ResolveActiveClinic;

use App\Context\Clinic\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\ResolveActiveClinic\ActiveClinicResult;
use App\Context\Clinic\Application\Query\ResolveActiveClinic\ActiveClinicResultType;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class ActiveClinicResultTest extends TestCase
{
    public function testNone(): void
    {
        $result = ActiveClinicResult::none();

        self::assertSame(ActiveClinicResultType::NONE, $result->type);
        self::assertNull($result->clinic);
        self::assertSame([], $result->clinics);
    }

    public function testSingle(): void
    {
        $clinic = $this->buildClinic('clinic-1');
        $result = ActiveClinicResult::single($clinic);

        self::assertSame(ActiveClinicResultType::SINGLE, $result->type);
        self::assertSame($clinic, $result->clinic);
        self::assertCount(1, $result->clinics);
    }

    public function testMultiple(): void
    {
        $clinics = [$this->buildClinic('clinic-1'), $this->buildClinic('clinic-2')];
        $result  = ActiveClinicResult::multiple($clinics);

        self::assertSame(ActiveClinicResultType::MULTIPLE, $result->type);
        self::assertNull($result->clinic);
        self::assertCount(2, $result->clinics);
    }

    private function buildClinic(string $id): AccessibleClinic
    {
        return new AccessibleClinic(
            clinicId: $id,
            clinicName: 'Clinic',
            clinicSlug: 'clinic',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
        );
    }
}
