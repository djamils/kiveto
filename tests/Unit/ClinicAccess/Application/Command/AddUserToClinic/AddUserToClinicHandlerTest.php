<?php

declare(strict_types=1);

namespace App\Tests\Unit\ClinicAccess\Application\Command\AddUserToClinic;

use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\ClinicAccess\Application\Command\AddUserToClinic\AddUserToClinic;
use App\ClinicAccess\Application\Command\AddUserToClinic\AddUserToClinicHandler;
use App\ClinicAccess\Application\Exception\ClinicMembershipAlreadyExistsException;
use App\ClinicAccess\Domain\ClinicMembership;
use App\ClinicAccess\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\ClinicAccess\Domain\ValueObject\ClinicMemberRole;
use App\ClinicAccess\Domain\ValueObject\ClinicMembershipEngagement;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class AddUserToClinicHandlerTest extends TestCase
{
    public function testAddUserToClinicSuccessfully(): void
    {
        $clinicId = '11111111-1111-1111-1111-111111111111';
        $userId   = '22222222-2222-2222-2222-222222222222';

        $clinic = $this->createStub(Clinic::class);
        $user   = $this->createStub(User::class);

        $clinicRepo = $this->createMock(ClinicRepositoryInterface::class);
        $clinicRepo->expects(self::once())
            ->method('findById')
            ->with(self::callback(static fn (ClinicId $id): bool => $clinicId === $id->toString()))
            ->willReturn($clinic)
        ;

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects(self::once())
            ->method('findById')
            ->with(self::callback(static fn (UserId $id): bool => $userId === $id->toString()))
            ->willReturn($user)
        ;

        $membershipRepo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->expects(self::once())
            ->method('existsByClinicAndUser')
            ->willReturn(false)
        ;
        $membershipRepo->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(ClinicMembership::class))
        ;

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn('01234567-89ab-cdef-0123-456789abcdef');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2025-01-01T12:00:00Z'));

        $handler = new AddUserToClinicHandler(
            $membershipRepo,
            $clinicRepo,
            $userRepo,
            $uuidGenerator,
            $clock,
        );

        $handler(new AddUserToClinic(
            clinicId: $clinicId,
            userId: $userId,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));

        self::assertTrue(true); // No exception thrown
    }

    public function testThrowsExceptionWhenClinicDoesNotExist(): void
    {
        $clinicId = '11111111-1111-1111-1111-111111111111';
        $userId   = '22222222-2222-2222-2222-222222222222';

        $clinicRepo = $this->createMock(ClinicRepositoryInterface::class);
        $clinicRepo->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $userRepo       = $this->createStub(UserRepositoryInterface::class);
        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $uuidGenerator  = $this->createStub(UuidGeneratorInterface::class);
        $clock          = $this->createStub(ClockInterface::class);

        $handler = new AddUserToClinicHandler(
            $membershipRepo,
            $clinicRepo,
            $userRepo,
            $uuidGenerator,
            $clock,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clinic with ID "' . $clinicId . '" does not exist.');

        $handler(new AddUserToClinic(
            clinicId: $clinicId,
            userId: $userId,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }

    public function testThrowsExceptionWhenUserDoesNotExist(): void
    {
        $clinicId = '11111111-1111-1111-1111-111111111111';
        $userId   = '22222222-2222-2222-2222-222222222222';

        $clinic = $this->createStub(Clinic::class);

        $clinicRepo = $this->createMock(ClinicRepositoryInterface::class);
        $clinicRepo->expects(self::once())
            ->method('findById')
            ->willReturn($clinic)
        ;

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects(self::once())
            ->method('findById')
            ->willReturn(null)
        ;

        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $uuidGenerator  = $this->createStub(UuidGeneratorInterface::class);
        $clock          = $this->createStub(ClockInterface::class);

        $handler = new AddUserToClinicHandler(
            $membershipRepo,
            $clinicRepo,
            $userRepo,
            $uuidGenerator,
            $clock,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID "' . $userId . '" does not exist.');

        $handler(new AddUserToClinic(
            clinicId: $clinicId,
            userId: $userId,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }

    public function testThrowsExceptionWhenMembershipAlreadyExists(): void
    {
        $clinicId = '11111111-1111-1111-1111-111111111111';
        $userId   = '22222222-2222-2222-2222-222222222222';

        $clinic = $this->createStub(Clinic::class);
        $user   = $this->createStub(User::class);

        $clinicRepo = $this->createMock(ClinicRepositoryInterface::class);
        $clinicRepo->expects(self::once())
            ->method('findById')
            ->willReturn($clinic)
        ;

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects(self::once())
            ->method('findById')
            ->willReturn($user)
        ;

        $membershipRepo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->expects(self::once())
            ->method('existsByClinicAndUser')
            ->willReturn(true)
        ;
        $membershipRepo->expects(self::never())->method('save');

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $clock         = $this->createStub(ClockInterface::class);

        $handler = new AddUserToClinicHandler(
            $membershipRepo,
            $clinicRepo,
            $userRepo,
            $uuidGenerator,
            $clock,
        );

        $this->expectException(ClinicMembershipAlreadyExistsException::class);

        $handler(new AddUserToClinic(
            clinicId: $clinicId,
            userId: $userId,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }
}
