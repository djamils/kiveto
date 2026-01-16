<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Query\GetUserMembershipInClinic;

use App\AccessControl\Application\Query\GetUserMembershipInClinic\GetUserMembershipInClinic;
use App\AccessControl\Application\Query\GetUserMembershipInClinic\GetUserMembershipInClinicHandler;
use App\AccessControl\Application\Query\GetUserMembershipInClinic\MembershipDetails;
use App\AccessControl\Domain\ClinicMembership;
use App\AccessControl\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\AccessControl\Domain\ValueObject\ClinicId;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\AccessControl\Domain\ValueObject\MembershipId;
use App\AccessControl\Domain\ValueObject\UserId;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class GetUserMembershipInClinicHandlerTest extends TestCase
{
    public function testHandlerReturnsNullWhenMembershipNotFound(): void
    {
        $repository = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repository->method('findByClinicAndUser')->willReturn(null);

        $clock = $this->createStub(ClockInterface::class);

        $handler = new GetUserMembershipInClinicHandler($repository, $clock);
        $query   = new GetUserMembershipInClinic(
            userId: '11111111-1111-1111-1111-111111111111',
            clinicId: '22222222-2222-2222-2222-222222222222',
        );

        $result = ($handler)($query);

        self::assertNull($result);
    }

    public function testHandlerReturnsMembershipDetailsWhenFound(): void
    {
        $now          = new \DateTimeImmutable('2024-06-15');
        $validFrom    = new \DateTimeImmutable('2024-01-01');
        $validUntil   = new \DateTimeImmutable('2025-01-01');
        $membershipId = MembershipId::fromString('11111111-1111-1111-1111-111111111111');

        $membership = ClinicMembership::create(
            id: $membershipId,
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            userId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: $validFrom,
            validUntil: $validUntil,
            createdAt: new \DateTimeImmutable('2024-01-01'),
        );

        $repository = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repository->method('findByClinicAndUser')->willReturn($membership);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $handler = new GetUserMembershipInClinicHandler($repository, $clock);
        $query   = new GetUserMembershipInClinic(
            userId: '33333333-3333-3333-3333-333333333333',
            clinicId: '22222222-2222-2222-2222-222222222222',
        );

        $result = ($handler)($query);

        self::assertInstanceOf(MembershipDetails::class, $result);
        self::assertSame('11111111-1111-1111-1111-111111111111', $result->membershipId);
        self::assertSame(ClinicMemberRole::VETERINARY, $result->role);
        self::assertSame(ClinicMembershipEngagement::EMPLOYEE, $result->engagement);
        self::assertSame(ClinicMembershipStatus::ACTIVE, $result->status);
        self::assertSame($validFrom, $result->validFrom);
        self::assertSame($validUntil, $result->validUntil);
        self::assertTrue($result->isEffectiveNow);
    }

    public function testHandlerSetsIsEffectiveNowBasedOnClock(): void
    {
        $now          = new \DateTimeImmutable('2025-06-15');
        $validFrom    = new \DateTimeImmutable('2024-01-01');
        $validUntil   = new \DateTimeImmutable('2025-01-01');
        $membershipId = MembershipId::fromString('11111111-1111-1111-1111-111111111111');

        $membership = ClinicMembership::create(
            id: $membershipId,
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            userId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            role: ClinicMemberRole::CLINIC_ADMIN,
            engagement: ClinicMembershipEngagement::CONTRACTOR,
            validFrom: $validFrom,
            validUntil: $validUntil,
            createdAt: new \DateTimeImmutable('2024-01-01'),
        );

        $repository = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repository->method('findByClinicAndUser')->willReturn($membership);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $handler = new GetUserMembershipInClinicHandler($repository, $clock);
        $query   = new GetUserMembershipInClinic(
            userId: '33333333-3333-3333-3333-333333333333',
            clinicId: '22222222-2222-2222-2222-222222222222',
        );

        $result = ($handler)($query);

        self::assertInstanceOf(MembershipDetails::class, $result);
        self::assertFalse($result->isEffectiveNow);
    }
}
