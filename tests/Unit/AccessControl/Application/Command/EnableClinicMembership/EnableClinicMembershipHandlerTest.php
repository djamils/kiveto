<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Command\EnableClinicMembership;

use App\AccessControl\Application\Command\EnableClinicMembership\EnableClinicMembership;
use App\AccessControl\Application\Command\EnableClinicMembership\EnableClinicMembershipHandler;
use App\AccessControl\Domain\ClinicMembership;
use App\AccessControl\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\AccessControl\Domain\ValueObject\ClinicId;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\AccessControl\Domain\ValueObject\ClinicMembershipStatus;
use App\AccessControl\Domain\ValueObject\MembershipId;
use App\AccessControl\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class EnableClinicMembershipHandlerTest extends TestCase
{
    public function testHandlerEnablesMembershipSuccessfully(): void
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

        $membership->disable();

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

        $handler = new EnableClinicMembershipHandler($repository);
        $command = new EnableClinicMembership(
            membershipId: '11111111-1111-1111-1111-111111111111',
        );

        ($handler)($command);

        self::assertSame(ClinicMembershipStatus::ACTIVE, $membership->status());
    }

    public function testHandlerThrowsExceptionWhenMembershipNotFound(): void
    {
        $repository = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new EnableClinicMembershipHandler($repository);
        $command = new EnableClinicMembership(
            membershipId: '11111111-1111-1111-1111-111111111111',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Membership "11111111-1111-1111-1111-111111111111" not found.');

        ($handler)($command);
    }
}
