<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\ScheduleAppointment;

use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointment;
use App\Context\Scheduling\Application\Command\ScheduleAppointment\ScheduleAppointmentHandler;
use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockFinderInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockReadModel;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ScheduleAppointmentHandlerTest extends TestCase
{
    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private MembershipEligibilityCheckerInterface&MockObject $membershipEligibilityChecker;
    private AppointmentConflictCheckerInterface&MockObject $conflictChecker;
    private UuidGeneratorInterface&MockObject $uuidGenerator;
    private ClockInterface&MockObject $clock;
    private PlanningBlockFinderInterface&MockObject $planningBlockFinder;
    private ClinicTimezoneResolverInterface&MockObject $timezoneResolver;
    private ScheduleAppointmentHandler $handler;

    protected function setUp(): void
    {
        $this->appointmentRepository        = $this->createMock(AppointmentRepositoryInterface::class);
        $this->membershipEligibilityChecker = $this->createMock(MembershipEligibilityCheckerInterface::class);
        $this->conflictChecker              = $this->createMock(AppointmentConflictCheckerInterface::class);
        $this->uuidGenerator                = $this->createMock(UuidGeneratorInterface::class);
        $this->clock                        = $this->createMock(ClockInterface::class);
        $this->planningBlockFinder          = $this->createMock(PlanningBlockFinderInterface::class);
        $this->timezoneResolver             = $this->createMock(ClinicTimezoneResolverInterface::class);

        $this->handler = new ScheduleAppointmentHandler(
            appointmentRepository: $this->appointmentRepository,
            membershipEligibilityChecker: $this->membershipEligibilityChecker,
            conflictChecker: $this->conflictChecker,
            uuidGenerator: $this->uuidGenerator,
            clock: $this->clock,
            planningBlockFinder: $this->planningBlockFinder,
            timezoneResolver: $this->timezoneResolver,
        );
    }

    public function testScheduleAppointmentSuccess(): void
    {
        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            practitionerUserId: '44444444-4444-4444-4444-444444444444',
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 30,
            reason: 'Consultation',
            notes: null,
        );

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
                ['VETERINARY', 'VETERINARY_ASSISTANT']
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

        $this->timezoneResolver->expects(self::once())
            ->method('resolveTimezone')
            ->willReturn(new \DateTimeZone('Europe/Paris'))
        ;

        $this->planningBlockFinder->expects(self::once())
            ->method('findActiveBlockFor')
            ->willReturn(null)
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

    #[AllowMockObjectsWithoutExpectations]
    public function testScheduleAppointmentWithLinkedAdmissionId(): void
    {
        $admissionId = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
        $command     = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            practitionerUserId: '44444444-4444-4444-4444-444444444444',
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 30,
            linkedAdmissionId: $admissionId,
        );

        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-30 12:00:00'));
        $this->membershipEligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(false);
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->planningBlockFinder->method('findActiveBlockFor')->willReturn(null);
        $this->uuidGenerator->method('generate')->willReturn('01234567-89ab-cdef-0123-456789abcdef');

        $savedAppointment = null;
        $this->appointmentRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Appointment $apt) use (&$savedAppointment): bool {
                $savedAppointment = $apt;

                return true;
            }))
        ;

        ($this->handler)($command);

        self::assertNotNull($savedAppointment);
        self::assertInstanceOf(Appointment::class, $savedAppointment);
        self::assertSame($admissionId, $savedAppointment->linkedAdmissionId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testScheduleAppointmentFailsWhenPractitionerNotEligible(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('User "44444444-4444-4444-4444-444444444444" is not eligible as practitioner');

        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
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

    #[AllowMockObjectsWithoutExpectations]
    public function testScheduleAppointmentFailsWhenOverlapDetected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'Practitioner "44444444-4444-4444-4444-444444444444" has an overlapping appointment',
        );

        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
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
            ->willReturn(true)
        ;

        ($this->handler)($command);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBlockNotAcceptingAppointmentsThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('conge');

        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            practitionerUserId: '44444444-4444-4444-4444-444444444444',
            startsAtUtc: new \DateTimeImmutable('2026-03-25 07:00:00'),
            durationMinutes: 30,
        );

        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-30 12:00:00'));
        $this->membershipEligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(false);
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->planningBlockFinder->method('findActiveBlockFor')->willReturn(
            new PlanningBlockReadModel(
                id: 'aabbccdd-0000-0000-0000-000000000001',
                type: PlanningBlockType::CONGE,
                capacityPerHour: 0,
                currentAppointmentCount: 0,
                acceptsAppointments: false,
            )
        );

        ($this->handler)($command);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBlockAtCapacityThrows(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('capacity reached');

        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            practitionerUserId: '44444444-4444-4444-4444-444444444444',
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 60,
        );

        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-30 12:00:00'));
        $this->membershipEligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(false);
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->planningBlockFinder->method('findActiveBlockFor')->willReturn(
            new PlanningBlockReadModel(
                id: 'aabbccdd-0000-0000-0000-000000000002',
                type: PlanningBlockType::CONSULTATION,
                capacityPerHour: 1,
                currentAppointmentCount: 1,
                acceptsAppointments: true,
            )
        );

        ($this->handler)($command);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testBlockWithCapacityAvailableSucceeds(): void
    {
        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            practitionerUserId: '44444444-4444-4444-4444-444444444444',
            startsAtUtc: new \DateTimeImmutable('2026-02-01 09:00:00'),
            durationMinutes: 30,
        );

        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-30 12:00:00'));
        $this->membershipEligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(false);
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->planningBlockFinder->method('findActiveBlockFor')->willReturn(
            new PlanningBlockReadModel(
                id: 'aabbccdd-0000-0000-0000-000000000003',
                type: PlanningBlockType::CONSULTATION,
                capacityPerHour: 3,
                currentAppointmentCount: 0,
                acceptsAppointments: true,
            )
        );
        $this->uuidGenerator->method('generate')->willReturn('01234567-89ab-cdef-0123-456789abcdef');
        $this->appointmentRepository->expects(self::once())->method('save');

        $id = ($this->handler)($command);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $id);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testNoBlockAllowsAppointment(): void
    {
        $command = new ScheduleAppointment(
            clinicId: '11111111-1111-1111-1111-111111111111',
            practitionerUserId: '44444444-4444-4444-4444-444444444444',
            startsAtUtc: new \DateTimeImmutable('2026-05-01 09:00:00'),
            durationMinutes: 30,
        );

        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-30 12:00:00'));
        $this->membershipEligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlap')->willReturn(false);
        $this->timezoneResolver->method('resolveTimezone')->willReturn(new \DateTimeZone('Europe/Paris'));
        $this->planningBlockFinder->method('findActiveBlockFor')->willReturn(null);
        $this->uuidGenerator->method('generate')->willReturn('01234567-89ab-cdef-0123-456789abcdef');
        $this->appointmentRepository->expects(self::once())->method('save');

        $id = ($this->handler)($command);
        self::assertSame('01234567-89ab-cdef-0123-456789abcdef', $id);
    }
}
