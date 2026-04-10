<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\ListClinicsForUser;

use App\Context\Clinic\Application\Port\ClinicMembershipReadRepositoryInterface;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\AccessibleClinic;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\ListClinicsForUser;
use App\Context\Clinic\Application\Query\Clinic\ListClinicsForUser\ListClinicsForUserHandler;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class ListClinicsForUserHandlerTest extends TestCase
{
    public function testReturnsClinicsFromReadRepository(): void
    {
        $clinic = new AccessibleClinic(
            clinicId: 'clinic-uuid',
            clinicName: 'Paris',
            clinicSlug: 'paris',
            clinicStatus: 'active',
            memberRole: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            isDefault: false,
        );

        $readRepo = $this->createStub(ClinicMembershipReadRepositoryInterface::class);
        $readRepo->method('findAccessibleClinicsForUser')
            ->with(self::callback(static fn (UserId $id): bool => 'user-uuid' === $id->toString()))
            ->willReturn([$clinic])
        ;

        $handler = new ListClinicsForUserHandler($readRepo);
        $result  = $handler(new ListClinicsForUser('user-uuid'));

        self::assertCount(1, $result);
        self::assertSame('clinic-uuid', $result[0]->clinicId);
    }
}
