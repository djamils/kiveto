<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduling\Application\Command\ScheduleAppointment;

use App\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointmentHandler;
use App\Scheduling\Application\Port\AnimalExistenceCheckerInterface;
use App\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Scheduling\Application\Port\OwnerExistenceCheckerInterface;
use App\Scheduling\Domain\Appointment;
use App\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Scheduling\Domain\ValueObject\AnimalId;
use App\Scheduling\Domain\ValueObject\ClinicId;
use App\Scheduling\Domain\ValueObject\OwnerId;
use App\Scheduling\Domain\ValueObject\TimeSlot;
use App\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

final class ScheduleAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface $appointmentRepository;
    private MembershipEligibilityCheckerInterface $membershipEligibilityChecker;
    private AppointmentConflictCheckerInterface $conflictChecker;
    private OwnerExistenceCheckerInterface $ownerExistenceChecker;
    private AnimalExistenceCheckerInterface $animalExistenceChecker;
    private UuidGeneratorInterface $uuidGenerator;
    private ClockInterface $clock;
    private ScheduleAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository        = $this->createMock(AppointmentRepositoryInterface::class);
        $this->membershipEligibilityChecker = $this->createMock(MembershipEligibilityCheckerInterface::class);
        $this->conflictChecker              = $this->createMock(AppointmentConflictCheckerInterface::class);
        $this->ownerExistenceChecker        = $this->createMock(OwnerExistenceCheckerInterface::class);
        $this->animalExistenceChecker       = $this->createMock(AnimalExistenceCheckerInterface::class);
        $this->uuidGenerator                = $this->createMock(UuidGeneratorInterface::class);
        $this->clock                        = $this->createMock(ClockInterface::class);

        $this->handler = new ScheduleAppointmentHandler(
            appointmentRepository: $this->appointmentRepository,
            membershipEligibilityChecker: $this->membershipEligibilityChecker,
            conflictChecker: $this->conflictChecker,
            ownerExistenceChecker: $this->ownerExistenceChecker,
            animalExistenceChecker: $this->animalExistenceChecker,
            uuidGenerator: $this->uuidGenerator,
            clock: $this->clock,
        );
    }

    public function testScheduleAppointmentWithPractitionerSuccess(): void
    {
        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            ownerId: '22222222-2222-2222-2222-222222222222',
            animalId: '33333333-3333-3333-3333-333333333333',
            practitionerUserId: '44444444-4444-4444-4444-444444444444',
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 30,
            reason: 'Consultation',
            notes: null,
        );

        $this->ownerExistenceChecker->expects(self::once())
            ->method('exists')
            ->with(self::callback(fn ($id) => $id instanceof OwnerId))
            ->willReturn(true)
        ;

        $this->animalExistenceChecker->expects(self::once())
            ->method('exists')
            ->with(self::callback(fn ($id) => $id instanceof AnimalId))
            ->willReturn(true)
        ;

        $this->clock->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-01-30 12:00:00'))
        ;

        $this->membershipEligibilityChecker->expects(self::once())
            ->method('isUserEligibleForClinicAt')
            ->with(
                self::callback(fn ($id) => $id instanceof UserId),
                self::callback(fn ($id) => $id instanceof ClinicId),
                self::anything(),
                ['VETERINARY', 'ASSISTANT_VETERINARY']
            )
            ->willReturn(true)
        ;

        $this->conflictChecker->expects(self::once())
            ->method('hasOverlap')
            ->with(
                self::callback(fn ($id) => $id instanceof ClinicId),
                self::callback(fn ($id) => $id instanceof UserId),
                self::callback(fn ($slot) => $slot instanceof TimeSlot),
                null
            )
            ->willReturn(false)
        ;

        $this->uuidGenerator->expects(self::once())
            ->method('generate')
            ->willReturn('01234567-89ab-cdef-0123-456789abcdef')
        ;

        $this->appointmentRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(fn ($apt) => $apt instanceof Appointment))
        ;

        $appointmentId = ($this->handler)($command);

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $appointmentId);
    }

    public function testScheduleAppointmentWithoutPractitionerSuccess(): void
    {
        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            ownerId: '22222222-2222-2222-2222-222222222222',
            animalId: '33333333-3333-3333-3333-333333333333',
            practitionerUserId: null,
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 30,
        );

        $this->ownerExistenceChecker->expects(self::once())
            ->method('exists')
            ->willReturn(true)
        ;

        $this->animalExistenceChecker->expects(self::once())
            ->method('exists')
            ->willReturn(true)
        ;

        $this->clock->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-01-30 12:00:00'))
        ;

        $this->membershipEligibilityChecker->expects(self::never())
            ->method('isUserEligibleForClinicAt')
        ;

        $this->conflictChecker->expects(self::never())
            ->method('hasOverlap')
        ;

        $this->uuidGenerator->expects(self::once())
            ->method('generate')
            ->willReturn('01234567-89ab-cdef-0123-456789abcdef')
        ;

        $this->appointmentRepository->expects(self::once())
            ->method('save')
        ;

        $appointmentId = ($this->handler)($command);

        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $appointmentId);
    }

    public function testScheduleAppointmentFailsWhenOwnerDoesNotExist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Owner with ID "22222222-2222-2222-2222-222222222222" does not exist.');

        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            ownerId: '22222222-2222-2222-2222-222222222222',
            animalId: null,
            practitionerUserId: null,
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 30,
        );

        $this->ownerExistenceChecker->expects(self::once())
            ->method('exists')
            ->willReturn(false)
        ;

        ($this->handler)($command);
    }

    public function testScheduleAppointmentFailsWhenAnimalDoesNotExist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Animal with ID "33333333-3333-3333-3333-333333333333" does not exist.');

        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            ownerId: null,
            animalId: '33333333-3333-3333-3333-333333333333',
            practitionerUserId: null,
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 30,
        );

        $this->animalExistenceChecker->expects(self::once())
            ->method('exists')
            ->willReturn(false)
        ;

        ($this->handler)($command);
    }

    public function testScheduleAppointmentFailsWhenPractitionerNotEligible(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User "44444444-4444-4444-4444-444444444444" is not eligible as practitioner');

        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            ownerId: null,
            animalId: null,
            practitionerUserId: '44444444-4444-4444-4444-444444444444',
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 30,
        );

        $this->clock->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-01-30 12:00:00'))
        ;

        $this->membershipEligibilityChecker->expects(self::once())
            ->method('isUserEligibleForClinicAt')
            ->willReturn(false)
        ;

        ($this->handler)($command);
    }

    public function testScheduleAppointmentFailsWhenOverlapDetected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Practitioner "44444444-4444-4444-4444-444444444444" has an overlapping appointment');

        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            ownerId: null,
            animalId: null,
            practitionerUserId: '44444444-4444-4444-4444-444444444444',
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 30,
        );

        $this->clock->expects(self::once())
            ->method('now')
            ->willReturn(new \DateTimeImmutable('2026-01-30 12:00:00'))
        ;

        $this->membershipEligibilityChecker->expects(self::once())
            ->method('isUserEligibleForClinicAt')
            ->willReturn(true)
        ;

        $this->conflictChecker->expects(self::once())
            ->method('hasOverlap')
            ->willReturn(true);

        ($this->handler)($command);
    }
}
