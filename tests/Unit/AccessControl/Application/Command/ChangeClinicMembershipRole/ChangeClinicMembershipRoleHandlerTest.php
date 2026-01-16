<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Command\ChangeClinicMembershipRole;

use App\AccessControl\Application\Command\ChangeClinicMembershipRole\ChangeClinicMembershipRole;
use App\AccessControl\Application\Command\ChangeClinicMembershipRole\ChangeClinicMembershipRoleHandler;
use App\AccessControl\Domain\ClinicMembership;
use App\AccessControl\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\AccessControl\Domain\ValueObject\ClinicId;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\MembershipId;
use App\AccessControl\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipRoleHandlerTest extends TestCase
{
    public function testHandlerChangesRoleSuccessfully(): void
    {
        $membershipId = MembershipId::fromString('11111111-1111-1111-1111-111111111111');

        $membership = ClinicMembership::create(
            id: $membershipId,
            clinicId: ClinicId::fromString('22222222-2222-2222-2222-222222222222'),
            userId: UserId::fromString('33333333-3333-3333-3333-333333333333'),
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            validFrom: new \DateTimeImmutable('2024-01-01'),
            validUntil: null,
            createdAt: new \DateTimeImmutable('2024-01-01'),
        );

        $repository = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('findById')
            ->with(self::callback(static function (mixed $id): bool {
                return $id instanceof MembershipId
                    && '11111111-1111-1111-1111-111111111111' === $id->toString();
            }))
            ->willReturn($membership)
        ;
        $repository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(ClinicMembership::class))
        ;

        $handler = new ChangeClinicMembershipRoleHandler($repository);
        $command = new ChangeClinicMembershipRole(
            membershipId: '11111111-1111-1111-1111-111111111111',
            role: ClinicMemberRole::CLINIC_ADMIN,
        );

        ($handler)($command);

        self::assertSame(ClinicMemberRole::CLINIC_ADMIN, $membership->role());
    }

    public function testHandlerThrowsExceptionWhenMembershipNotFound(): void
    {
        $repository = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new ChangeClinicMembershipRoleHandler($repository);
        $command = new ChangeClinicMembershipRole(
            membershipId: '11111111-1111-1111-1111-111111111111',
            role: ClinicMemberRole::CLINIC_ADMIN,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Membership "11111111-1111-1111-1111-111111111111" not found.');

        ($handler)($command);
    }
}
