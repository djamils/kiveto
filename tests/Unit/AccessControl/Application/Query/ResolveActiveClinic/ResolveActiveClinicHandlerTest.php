<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ResolveActiveClinic;

use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\AccessControl\Application\Query\ResolveActiveClinic\ActiveClinicResultType;
use App\AccessControl\Application\Query\ResolveActiveClinic\ResolveActiveClinic;
use App\AccessControl\Application\Query\ResolveActiveClinic\ResolveActiveClinicHandler;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\Shared\Application\Bus\QueryBusInterface;
use PHPUnit\Framework\TestCase;

final class ResolveActiveClinicHandlerTest extends TestCase
{
    public function testReturnsNoneWhenUserHasNoClinics(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn([]);

        $handler = new ResolveActiveClinicHandler($queryBus);
        $result  = $handler(new ResolveActiveClinic('user-id'));

        self::assertSame(ActiveClinicResultType::NONE, $result->type);
        self::assertNull($result->clinic);
        self::assertCount(0, $result->clinics);
    }

    public function testReturnsSingleWhenUserHasOneClinic(): void
    {
        $clinic = new AccessibleClinic(
            clinicId: '11111111-1111-1111-1111-111111111111',
            clinicName: 'Test Clinic',
            clinicSlug: 'test-clinic',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable(),
            validUntil: null,
        );

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn([$clinic]);

        $handler = new ResolveActiveClinicHandler($queryBus);
        $result  = $handler(new ResolveActiveClinic('user-id'));

        self::assertSame(ActiveClinicResultType::SINGLE, $result->type);
        self::assertSame($clinic, $result->clinic);
        self::assertCount(1, $result->clinics);
    }

    public function testReturnsMultipleWhenUserHasManyClinics(): void
    {
        $clinic1 = new AccessibleClinic(
            clinicId: '11111111-1111-1111-1111-111111111111',
            clinicName: 'Clinic 1',
            clinicSlug: 'clinic-1',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable(),
            validUntil: null,
        );

        $clinic2 = new AccessibleClinic(
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Clinic 2',
            clinicSlug: 'clinic-2',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: new \DateTimeImmutable(),
            validUntil: null,
        );

        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn([$clinic1, $clinic2]);

        $handler = new ResolveActiveClinicHandler($queryBus);
        $result  = $handler(new ResolveActiveClinic('user-id'));

        self::assertSame(ActiveClinicResultType::MULTIPLE, $result->type);
        self::assertNull($result->clinic);
        self::assertCount(2, $result->clinics);
    }
}
