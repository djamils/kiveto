<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\AccessControl\Application\Command\ChangeClinicMembershipValidityWindow;

use App\System\AccessControl\Application\Command\ChangeClinicMembershipValidityWindow\ChangeClinicMembershipValidityWindow as ChangeValidityWindow; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\System\AccessControl\Application\Command\ChangeClinicMembershipValidityWindow\ChangeClinicMembershipValidityWindowHandler as ChangeValidityWindowHandler; // phpcs:ignore Generic.Files.LineLength.TooLong
use App\System\AccessControl\Domain\ClinicMembership;
use App\System\AccessControl\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\System\AccessControl\Domain\ValueObject\ClinicId;
use App\System\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\System\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\System\AccessControl\Domain\ValueObject\MembershipId;
use App\System\AccessControl\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class ChangeClinicMembershipValidityWindowHandlerTest extends TestCase
{
    public function testHandlerChangesValidityWindowSuccessfully(): void
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

        $newValidFrom  = new \DateTimeImmutable('2024-06-01');
        $newValidUntil = new \DateTimeImmutable('2025-06-01');

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

        $handler = new ChangeValidityWindowHandler($repository);
        $command = new ChangeValidityWindow(
            membershipId: '11111111-1111-1111-1111-111111111111',
            validFrom: $newValidFrom,
            validUntil: $newValidUntil,
        );

        ($handler)($command);

        self::assertSame($newValidFrom, $membership->validFrom());
        self::assertSame($newValidUntil, $membership->validUntil());
    }

    public function testHandlerThrowsExceptionWhenMembershipNotFound(): void
    {
        $repository = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new ChangeValidityWindowHandler($repository);
        $command = new ChangeValidityWindow(
            membershipId: '11111111-1111-1111-1111-111111111111',
            validFrom: new \DateTimeImmutable('2024-06-01'),
            validUntil: new \DateTimeImmutable('2025-06-01'),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Membership "11111111-1111-1111-1111-111111111111" not found.');

        ($handler)($command);
    }
}
