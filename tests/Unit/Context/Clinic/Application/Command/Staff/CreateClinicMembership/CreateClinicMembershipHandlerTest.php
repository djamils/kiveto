<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\CreateClinicMembership;

use App\Context\Clinic\Application\Command\Staff\CreateClinicMembership\CreateClinicMembership;
use App\Context\Clinic\Application\Command\Staff\CreateClinicMembership\CreateClinicMembershipHandler;
use App\Context\Clinic\Application\Exception\ClinicMembershipAlreadyExistsException;
use App\Context\Clinic\Application\Port\UserExistenceCheckerInterface;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ClinicMembership;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class CreateClinicMembershipHandlerTest extends TestCase
{
    private const string CLINIC_ID     = '01912345-6789-7abc-8def-000000000001';
    private const string USER_ID       = '01912345-6789-7abc-8def-000000000002';
    private const string MEMBERSHIP_ID = '01912345-6789-7abc-8def-000000000003';

    public function testCreateMembershipSuccessfully(): void
    {
        $clinicRepo = $this->createStub(ClinicRepositoryInterface::class);
        $clinicRepo->method('findById')->willReturn($this->buildClinic());

        $userChecker = $this->createStub(UserExistenceCheckerInterface::class);
        $userChecker->method('exists')->willReturn(true);

        $membershipRepo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('existsByClinicAndUser')->willReturn(false);
        $membershipRepo->expects(self::once())->method('save')->with(self::isInstanceOf(ClinicMembership::class));

        $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
        $uuidGenerator->method('generate')->willReturn(self::MEMBERSHIP_ID);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-01T00:00:00Z'));

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')->with([], self::isInstanceOf(DomainEventInterface::class));

        $handler = new CreateClinicMembershipHandler(
            $membershipRepo,
            $clinicRepo,
            $userChecker,
            $uuidGenerator,
            $clock,
            new DomainEventPublisher($eventBus),
        );

        $handler(new CreateClinicMembership(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }

    public function testThrowsWhenClinicNotFound(): void
    {
        $clinicRepo = $this->createStub(ClinicRepositoryInterface::class);
        $clinicRepo->method('findById')->willReturn(null);

        $handler = new CreateClinicMembershipHandler(
            $this->createStub(ClinicMembershipRepositoryInterface::class),
            $clinicRepo,
            $this->createStub(UserExistenceCheckerInterface::class),
            $this->createStub(UuidGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $handler(new CreateClinicMembership(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $clinicRepo = $this->createStub(ClinicRepositoryInterface::class);
        $clinicRepo->method('findById')->willReturn($this->buildClinic());

        $userChecker = $this->createStub(UserExistenceCheckerInterface::class);
        $userChecker->method('exists')->willReturn(false);

        $handler = new CreateClinicMembershipHandler(
            $this->createStub(ClinicMembershipRepositoryInterface::class),
            $clinicRepo,
            $userChecker,
            $this->createStub(UuidGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        $handler(new CreateClinicMembership(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }

    public function testThrowsWhenMembershipAlreadyExists(): void
    {
        $clinicRepo = $this->createStub(ClinicRepositoryInterface::class);
        $clinicRepo->method('findById')->willReturn($this->buildClinic());

        $userChecker = $this->createStub(UserExistenceCheckerInterface::class);
        $userChecker->method('exists')->willReturn(true);

        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('existsByClinicAndUser')->willReturn(true);

        $handler = new CreateClinicMembershipHandler(
            $membershipRepo,
            $clinicRepo,
            $userChecker,
            $this->createStub(UuidGeneratorInterface::class),
            $this->createStub(ClockInterface::class),
            new DomainEventPublisher($this->createStub(EventBusInterface::class)),
        );

        $this->expectException(ClinicMembershipAlreadyExistsException::class);

        $handler(new CreateClinicMembership(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
        ));
    }

    private function buildClinic(): Clinic
    {
        return Clinic::reconstitute(
            ClinicId::fromString(self::CLINIC_ID),
            'Test Clinic',
            ClinicSlug::fromString('test-clinic'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            \App\Context\Clinic\Domain\ValueObject\ClinicStatus::ACTIVE,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-01'),
            null,
        );
    }
}
