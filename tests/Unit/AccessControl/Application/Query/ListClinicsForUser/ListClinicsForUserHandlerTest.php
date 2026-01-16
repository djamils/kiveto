<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\ListClinicsForUser;

use App\AccessControl\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\AccessControl\Application\Query\ListClinicsForUser\AccessibleClinic;
use App\AccessControl\Application\Query\ListClinicsForUser\ListClinicsForUser;
use App\AccessControl\Application\Query\ListClinicsForUser\ListClinicsForUserHandler;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use PHPUnit\Framework\TestCase;

final class ListClinicsForUserHandlerTest extends TestCase
{
    public function testHandlerReturnsAccessibleClinics(): void
    {
        $clinic1 = new AccessibleClinic(
            clinicId: '11111111-1111-1111-1111-111111111111',
            clinicName: 'Clinic 1',
            clinicSlug: 'clinic-1',
            clinicStatus: 'ACTIVE',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
        );

        $clinic2 = new AccessibleClinic(
            clinicId: '22222222-2222-2222-2222-222222222222',
            clinicName: 'Clinic 2',
            clinicSlug: 'clinic-2',
            clinicStatus: 'ACTIVE',
            memberRole: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
        );

        $repository = $this->createStub(ClinicMembershipReadRepositoryInterface::class);
        $repository->method('findAccessibleClinicsForUser')->willReturn([$clinic1, $clinic2]);

        $handler = new ListClinicsForUserHandler($repository);
        $query   = new ListClinicsForUser(userId: '33333333-3333-3333-3333-333333333333');

        $result = ($handler)($query);

        self::assertCount(2, $result);
        self::assertSame($clinic1, $result[0]);
        self::assertSame($clinic2, $result[1]);
    }

    public function testHandlerReturnsEmptyArrayWhenNoAccess(): void
    {
        $repository = $this->createStub(ClinicMembershipReadRepositoryInterface::class);
        $repository->method('findAccessibleClinicsForUser')->willReturn([]);

        $handler = new ListClinicsForUserHandler($repository);
        $query   = new ListClinicsForUser(userId: '33333333-3333-3333-3333-333333333333');

        $result = ($handler)($query);

        self::assertCount(0, $result);
    }

    public function testHandlerCallsRepositoryWithCorrectUserId(): void
    {
        $repository = $this->createMock(ClinicMembershipReadRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findAccessibleClinicsForUser')
            ->with(self::callback(static function (mixed $userId): bool {
                return $userId instanceof \App\AccessControl\Domain\ValueObject\UserId
                    && '44444444-4444-4444-4444-444444444444' === $userId->toString();
            }))
            ->willReturn([])
        ;

        $handler = new ListClinicsForUserHandler($repository);
        $query   = new ListClinicsForUser(userId: '44444444-4444-4444-4444-444444444444');

        ($handler)($query);
    }
}
