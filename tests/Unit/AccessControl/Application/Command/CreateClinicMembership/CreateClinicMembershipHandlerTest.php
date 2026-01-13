<?php

declare(strict_types=1);

namespace App\Tests\Unit\AccessControl\Application\Command\CreateClinicMembership;

use App\AccessControl\Application\Command\CreateClinicMembership\CreateClinicMembership;
use App\AccessControl\Application\Command\CreateClinicMembership\CreateClinicMembershipHandler;
use App\AccessControl\Application\Exception\ClinicMembershipAlreadyExistsException;
use App\AccessControl\Domain\ClinicMembership;
use App\AccessControl\Domain\Repository\ClinicMembershipRepositoryInterface;
use App\AccessControl\Domain\ValueObject\ClinicMemberRole;
use App\AccessControl\Domain\ValueObject\ClinicMembershipEngagement;
use App\Clinic\Domain\Clinic;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class CreateClinicMembershipHandlerTest extends TestCase
{
    public function testCreateClinicMembershipSuccessfully(): void
    {
        $clinicId = '11111111-1111-1111-1111-111111111111';
        $userId   = '22222222-2222-2222-2222-222222222222';

        $clinic = Clinic::reconstitute(
            id: ClinicId::fromString($clinicId),
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            status: ClinicStatus::ACTIVE,
            createdAt: new \DateTimeImmutable('2024-01-01'),
            updatedAt: new \DateTimeImmutable('2024-01-01'),
        );

        $user = User::reconstitute(
            id: UserId::fromString($userId),
            email: 'test@example.com',
            passwordHash: 'hash',
            createdAt: new \DateTimeImmutable('2024-01-01'),
            status: \App\IdentityAccess\Domain\ValueObject\UserStatus::ACTIVE,
            emailVerifiedAt: new \DateTimeImmutable('2024-01-01'),
            type: \App\IdentityAccess\Domain\ValueObject\UserType::PORTAL,
        );

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

        $uuidGenerator = $this->createMock(UuidGeneratorInterface::class);
        $uuidGenerator->expects(self::once())->method('generate')->willReturn('01234567-89ab-cdef-0123-456789abcdef');

        $clock = $this->createMock(ClockInterface::class);
        $clock->expects(self::atLeastOnce())->method('now')->willReturn(new \DateTimeImmutable('2025-01-01T12:00:00Z'));

        $handler = new CreateClinicMembershipHandler(
            $membershipRepo,
            $clinicRepo,
            $userRepo,
            $uuidGenerator,
            $clock,
        );

        $handler(new CreateClinicMembership(
            clinicId: $clinicId,
            userId: $userId,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));

        // Test passes if no exception is thrown
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

        $handler = new CreateClinicMembershipHandler(
            $membershipRepo,
            $clinicRepo,
            $userRepo,
            $uuidGenerator,
            $clock,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clinic with ID "' . $clinicId . '" does not exist.');

        $handler(new CreateClinicMembership(
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

        $clinic = Clinic::reconstitute(
            id: ClinicId::fromString($clinicId),
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            status: ClinicStatus::ACTIVE,
            createdAt: new \DateTimeImmutable('2024-01-01'),
            updatedAt: new \DateTimeImmutable('2024-01-01'),
        );

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

        $handler = new CreateClinicMembershipHandler(
            $membershipRepo,
            $clinicRepo,
            $userRepo,
            $uuidGenerator,
            $clock,
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User with ID "' . $userId . '" does not exist.');

        $handler(new CreateClinicMembership(
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

        $clinic = Clinic::reconstitute(
            id: ClinicId::fromString($clinicId),
            name: 'Test Clinic',
            slug: ClinicSlug::fromString('test-clinic'),
            timeZone: TimeZone::fromString('Europe/Paris'),
            locale: Locale::fromString('fr-FR'),
            status: ClinicStatus::ACTIVE,
            createdAt: new \DateTimeImmutable('2024-01-01'),
            updatedAt: new \DateTimeImmutable('2024-01-01'),
        );

        $user = User::reconstitute(
            id: UserId::fromString($userId),
            email: 'test@example.com',
            passwordHash: 'hash',
            createdAt: new \DateTimeImmutable('2024-01-01'),
            status: \App\IdentityAccess\Domain\ValueObject\UserStatus::ACTIVE,
            emailVerifiedAt: new \DateTimeImmutable('2024-01-01'),
            type: \App\IdentityAccess\Domain\ValueObject\UserType::PORTAL,
        );

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

        $handler = new CreateClinicMembershipHandler(
            $membershipRepo,
            $clinicRepo,
            $userRepo,
            $uuidGenerator,
            $clock,
        );

        $this->expectException(ClinicMembershipAlreadyExistsException::class);

        $handler(new CreateClinicMembership(
            clinicId: $clinicId,
            userId: $userId,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }
}
