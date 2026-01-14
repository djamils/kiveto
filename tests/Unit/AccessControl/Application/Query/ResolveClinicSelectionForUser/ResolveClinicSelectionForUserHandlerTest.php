<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ResolveClinicSelectionForUser;

use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\AccessControl\Application\Query\ResolveClinicSelectionForUser\ClinicSelectionType;
use App\AccessControl\Application\Query\ResolveClinicSelectionForUser\ResolveClinicSelectionForUser;
use App\AccessControl\Application\Query\ResolveClinicSelectionForUser\ResolveClinicSelectionForUserHandler;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\Shared\Application\Bus\QueryBusInterface;
use PHPUnit\Framework\TestCase;

final class ResolveClinicSelectionForUserHandlerTest extends TestCase
{
    public function testReturnsNoneWhenUserHasNoClinics(): void
    {
        $queryBus = $this->createStub(QueryBusInterface::class);
        $queryBus->method('ask')->willReturn([]);

        $handler  = new ResolveClinicSelectionForUserHandler($queryBus);
        $decision = $handler(new ResolveClinicSelectionForUser('user-id'));

        self::assertSame(ClinicSelectionType::NONE, $decision->type);
        self::assertNull($decision->singleClinic);
        self::assertCount(0, $decision->clinics);
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

        $handler  = new ResolveClinicSelectionForUserHandler($queryBus);
        $decision = $handler(new ResolveClinicSelectionForUser('user-id'));

        self::assertSame(ClinicSelectionType::SINGLE, $decision->type);
        self::assertSame($clinic, $decision->singleClinic);
        self::assertCount(1, $decision->clinics);
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

        $handler  = new ResolveClinicSelectionForUserHandler($queryBus);
        $decision = $handler(new ResolveClinicSelectionForUser('user-id'));

        self::assertSame(ClinicSelectionType::MULTIPLE, $decision->type);
        self::assertNull($decision->singleClinic);
        self::assertCount(2, $decision->clinics);
    }
}
