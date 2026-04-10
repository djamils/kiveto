<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic;

use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\GetUserMembershipInClinic;
use App\Context\Clinic\Application\Query\Staff\GetUserMembershipInClinic\GetUserMembershipInClinicHandler;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipId;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipStatus;
use App\Context\Clinic\Domain\Staff\ValueObject\UserId;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class GetUserMembershipInClinicHandlerTest extends TestCase
{
    public function testReturnsMembershipDetails(): void
    {
        $membership = ClinicMembership::reconstitute(
            id: ClinicMembershipId::fromString('01912345-6789-7abc-8def-000000000001'),
            clinicId: ClinicId::fromString('01912345-6789-7abc-8def-000000000002'),
            userId: UserId::fromString('01912345-6789-7abc-8def-000000000003'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            status: ClinicMembershipStatus::ACTIVE,
            validFrom: new \DateTimeImmutable('2026-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );

        $repo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repo->method('findByClinicAndUser')->willReturn($membership);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-06-01'));

        $handler = new GetUserMembershipInClinicHandler($repo, $clock);
        $result  = $handler(new GetUserMembershipInClinic(
            userId: '01912345-6789-7abc-8def-000000000003',
            clinicId: '01912345-6789-7abc-8def-000000000002',
        ));

        self::assertNotNull($result);
        self::assertSame('01912345-6789-7abc-8def-000000000001', $result->membershipId);
        self::assertSame(ClinicMemberRole::VETERINARY, $result->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $result->engagement);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $result->status);
        self::assertTrue($result->isEffectiveNow);
    }

    public function testReturnsNullWhenNotFound(): void
    {
        $repo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repo->method('findByClinicAndUser')->willReturn(null);

        $clock = $this->createStub(ClockInterface::class);

        $handler = new GetUserMembershipInClinicHandler($repo, $clock);
        $result  = $handler(new GetUserMembershipInClinic('user-uuid', 'clinic-uuid'));

        self::assertNull($result);
    }
}
