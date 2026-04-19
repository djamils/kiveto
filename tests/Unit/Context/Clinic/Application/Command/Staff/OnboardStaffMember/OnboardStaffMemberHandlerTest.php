<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Command\Staff\OnboardStaffMember;

use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMember;
use App\Context\Clinic\Application\Command\Staff\OnboardStaffMember\OnboardStaffMemberHandler;
use App\Context\Clinic\Application\Exception\ClinicMembershipAlreadyExistsException;
use App\Context\Clinic\Application\Port\UserExistenceCheckerInterface;
use App\Context\Clinic\Domain\Clinic;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\Staff\Repository\ClinicMembershipRepositoryInterface;
use App\Context\Clinic\Domain\Staff\Repository\StaffProfileRepositoryInterface;
use App\Context\Clinic\Domain\Staff\StaffProfile;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMemberRole;
use App\Context\Clinic\Domain\Staff\ValueObject\ClinicMembershipEngagement;
use App\Context\Clinic\Domain\Staff\ValueObject\ProfessionalTitle;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class OnboardStaffMemberHandlerTest extends TestCase
{
    private const string CLINIC_ID    = '01960000-0000-7000-8000-000000000001';
    private const string USER_ID      = '01912345-6789-7abc-8def-000000000002';
    private const string PROFILE_UUID = 'ffffffff-ffff-7fff-bfff-000000000001';
    private const string MEMBER_UUID  = 'ffffffff-ffff-7fff-bfff-000000000002';

    public function testOnboardVeterinaryMemberSuccessfullyReturnsProfileId(): void
    {
        $clinicRepo = $this->createStub(ClinicRepositoryInterface::class);
        $clinicRepo->method('findById')->willReturn($this->buildClinic());

        $membershipRepo = $this->createMock(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('existsByClinicAndUser')->willReturn(false);
        $membershipRepo->expects(self::once())->method('save');

        $profileRepo = $this->createMock(StaffProfileRepositoryInterface::class);
        $profileRepo->expects(self::once())->method('save')
            ->with(self::isInstanceOf(StaffProfile::class))
        ;

        $handler = $this->makeHandler($clinicRepo, $membershipRepo, $profileRepo);

        $profileId = $handler(new OnboardStaffMember(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Sophie',
            lastName: 'Rousseau',
            displayName: 'Dr. Rousseau',
            agendaColor: '#1a2b3c',
            sortOrder: 0,
            isVisibleInAgenda: true,
        ));

        self::assertSame(self::PROFILE_UUID, $profileId);
    }

    public function testOnboardAssistantCreatesProfileWithNullCredentials(): void
    {
        $clinicRepo = $this->createStub(ClinicRepositoryInterface::class);
        $clinicRepo->method('findById')->willReturn($this->buildClinic());

        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('existsByClinicAndUser')->willReturn(false);

        $savedProfile = null;
        $profileRepo  = $this->createMock(StaffProfileRepositoryInterface::class);
        $profileRepo->expects(self::once())->method('save')
            ->willReturnCallback(static function (StaffProfile $p) use (&$savedProfile): void {
                $savedProfile = $p;
            })
        ;

        $handler = $this->makeHandler($clinicRepo, $membershipRepo, $profileRepo);

        $handler(new OnboardStaffMember(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::VETERINARY_ASSISTANT,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Jean',
            lastName: 'Martin',
            displayName: 'Jean Martin',
            agendaColor: '#ff0000',
            sortOrder: 1,
            isVisibleInAgenda: true,
        ));

        self::assertNotNull($savedProfile);
        self::assertFalse($savedProfile->hasVeterinaryCredentials());
    }

    public function testOnboardRejectsManagerRole(): void
    {
        $clinicRepo     = $this->createStub(ClinicRepositoryInterface::class);
        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $profileRepo    = $this->createStub(StaffProfileRepositoryInterface::class);

        $handler = $this->makeHandler($clinicRepo, $membershipRepo, $profileRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MANAGER');

        $handler(new OnboardStaffMember(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::MANAGER,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Admin',
            lastName: 'User',
            displayName: 'Admin',
            agendaColor: '#111111',
            sortOrder: 0,
            isVisibleInAgenda: false,
        ));
    }

    public function testOnboardRejectsAlreadyExistingMembership(): void
    {
        $clinicRepo = $this->createStub(ClinicRepositoryInterface::class);
        $clinicRepo->method('findById')->willReturn($this->buildClinic());

        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('existsByClinicAndUser')->willReturn(true);

        $profileRepo = $this->createMock(StaffProfileRepositoryInterface::class);
        $profileRepo->expects(self::never())->method('save');

        $handler = $this->makeHandler($clinicRepo, $membershipRepo, $profileRepo);

        $this->expectException(ClinicMembershipAlreadyExistsException::class);

        $handler(new OnboardStaffMember(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Sophie',
            lastName: 'Rousseau',
            displayName: 'Dr. Rousseau',
            agendaColor: '#1a2b3c',
            sortOrder: 0,
            isVisibleInAgenda: true,
        ));
    }

    public function testOnboardVeterinaryWithCredentials(): void
    {
        $clinicRepo = $this->createStub(ClinicRepositoryInterface::class);
        $clinicRepo->method('findById')->willReturn($this->buildClinic());

        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $membershipRepo->method('existsByClinicAndUser')->willReturn(false);

        $savedProfile = null;
        $profileRepo  = $this->createMock(StaffProfileRepositoryInterface::class);
        $profileRepo->expects(self::once())->method('save')
            ->willReturnCallback(static function (StaffProfile $p) use (&$savedProfile): void {
                $savedProfile = $p;
            })
        ;

        $handler = $this->makeHandler($clinicRepo, $membershipRepo, $profileRepo);

        $handler(new OnboardStaffMember(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Sophie',
            lastName: 'Rousseau',
            displayName: 'Dr. Rousseau',
            agendaColor: '#1a2b3c',
            sortOrder: 0,
            isVisibleInAgenda: true,
            registrationNumber: 'FR-12345',
            professionalTitle: ProfessionalTitle::DR,
        ));

        self::assertNotNull($savedProfile);
        self::assertTrue($savedProfile->hasVeterinaryCredentials());
        self::assertSame('FR-12345', $savedProfile->credentials()?->registrationNumber()->toString());
    }

    public function testOnboardThrowsWhenClinicNotFound(): void
    {
        $clinicRepo = $this->createStub(ClinicRepositoryInterface::class);
        $clinicRepo->method('findById')->willReturn(null);

        $membershipRepo = $this->createStub(ClinicMembershipRepositoryInterface::class);
        $profileRepo    = $this->createStub(StaffProfileRepositoryInterface::class);

        $handler = $this->makeHandler($clinicRepo, $membershipRepo, $profileRepo);

        $this->expectException(\InvalidArgumentException::class);

        $handler(new OnboardStaffMember(
            clinicId: self::CLINIC_ID,
            userId: self::USER_ID,
            role: ClinicMemberRole::VETERINARY,
            engagement: ClinicMembershipEngagement::EMPLOYEE,
            firstName: 'Sophie',
            lastName: 'Rousseau',
            displayName: 'Dr. Rousseau',
            agendaColor: '#1a2b3c',
            sortOrder: 0,
            isVisibleInAgenda: true,
        ));
    }

    private function makeHandler(
        ClinicRepositoryInterface $clinicRepo,
        ClinicMembershipRepositoryInterface $membershipRepo,
        StaffProfileRepositoryInterface $profileRepo,
        ?UserExistenceCheckerInterface $userChecker = null,
        ?UuidGeneratorInterface $uuidGenerator = null,
        ?ClockInterface $clock = null,
        ?DomainEventPublisher $publisher = null,
    ): OnboardStaffMemberHandler {
        if (null === $userChecker) {
            $userChecker = $this->createStub(UserExistenceCheckerInterface::class);
            $userChecker->method('exists')->willReturn(true);
        }

        if (null === $uuidGenerator) {
            $uuidGenerator = $this->createStub(UuidGeneratorInterface::class);
            $uuidGenerator->method('generate')->willReturnOnConsecutiveCalls(self::MEMBER_UUID, self::PROFILE_UUID);
        }

        if (null === $clock) {
            $clock = $this->createStub(ClockInterface::class);
            $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        }

        $publisher ??= new DomainEventPublisher($this->createStub(EventBusInterface::class));

        return new OnboardStaffMemberHandler(
            clinicRepository: $clinicRepo,
            membershipRepository: $membershipRepo,
            profileRepository: $profileRepo,
            userExistenceChecker: $userChecker,
            uuidGenerator: $uuidGenerator,
            clock: $clock,
            domainEventPublisher: $publisher,
        );
    }

    private function buildClinic(): Clinic
    {
        return Clinic::reconstitute(
            ClinicId::fromString(self::CLINIC_ID),
            'Test Clinic',
            ClinicSlug::fromString('test-clinic'),
            TimeZone::fromString('Europe/Paris'),
            Locale::fromString('fr-FR'),
            ClinicStatus::ACTIVE,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-01-01'),
            null,
        );
    }
}
