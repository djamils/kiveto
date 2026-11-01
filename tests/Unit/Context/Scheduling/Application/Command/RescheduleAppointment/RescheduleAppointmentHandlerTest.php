<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\RescheduleAppointment;

use App\Context\Scheduling\Application\Command\RescheduleAppointment\RescheduleAppointment;
use App\Context\Scheduling\Application\Command\RescheduleAppointment\RescheduleAppointmentHandler;
use App\Context\Scheduling\Application\Port\AppointmentConflictCheckerInterface;
use App\Context\Scheduling\Application\Port\ClinicTimezoneResolverInterface;
use App\Context\Scheduling\Application\Port\MembershipEligibilityCheckerInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockFinderInterface;
use App\Context\Scheduling\Application\Port\PlanningBlockReadModel;
use App\Context\Scheduling\Application\Service\PlanningBlockGate;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettings;
use App\Context\Scheduling\Domain\Booking\OnlineBookingSettingsRepositoryInterface;
use App\Context\Scheduling\Domain\Exception\AppointmentNotModifiableException;
use App\Context\Scheduling\Domain\Exception\BlockRefusesAppointmentsException;
use App\Context\Scheduling\Domain\Exception\PractitionerNotBookableException;
use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use App\Tests\Unit\Context\Scheduling\Support\RecordingDayLock;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RescheduleAppointmentHandlerTest extends TestCase
{
    private const string CLINIC_ID      = '11111111-1111-1111-1111-111111111111';
    private const string APPOINTMENT_ID = '22222222-2222-2222-2222-222222222222';
    private const string PRACTITIONER   = '33333333-3333-3333-3333-333333333333';
    private const string OTHER_PRACT    = '44444444-4444-4444-4444-444444444444';

    private AppointmentRepositoryInterface&MockObject $repository;
    private AppointmentConflictCheckerInterface&MockObject $conflictChecker;
    private MembershipEligibilityCheckerInterface&MockObject $eligibilityChecker;
    private ClockInterface&MockObject $clock;
    private OnlineBookingSettingsRepositoryInterface&MockObject $settings;
    private PlanningBlockFinderInterface&MockObject $blockFinder;
    private RecordingDayLock $dayLock;
    private RescheduleAppointmentHandler $handler;
    private CapturingEventBus $events;

    protected function setUp(): void
    {
        $this->repository         = $this->createMock(AppointmentRepositoryInterface::class);
        $this->conflictChecker    = $this->createMock(AppointmentConflictCheckerInterface::class);
        $this->eligibilityChecker = $this->createMock(MembershipEligibilityCheckerInterface::class);
        $this->clock              = $this->createMock(ClockInterface::class);
        $this->settings           = $this->createMock(OnlineBookingSettingsRepositoryInterface::class);
        $this->blockFinder        = $this->createMock(PlanningBlockFinderInterface::class);

        $this->handler = new RescheduleAppointmentHandler(
            appointmentRepository: $this->repository,
            conflictChecker: $this->conflictChecker,
            membershipEligibilityChecker: $this->eligibilityChecker,
            clock: $this->clock,
            domainEventPublisher: new DomainEventPublisher($this->events = new CapturingEventBus()),
            onlineBookingSettings: $this->settings,
            timezoneResolver: new class implements ClinicTimezoneResolverInterface {
                public function resolveTimezone(ClinicId $clinicId): \DateTimeZone
                {
                    return new \DateTimeZone('Europe/Paris');
                }
            },
            dayLock: $this->dayLock = new RecordingDayLock(),
            planningBlockGate: new PlanningBlockGate($this->blockFinder),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRescheduleHappyPath(): void
    {
        $appointment = $this->createFutureAppointment();

        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlapLocked')->willReturn(false);
        $this->repository->expects(self::once())->method('save');
        // A confirmed booking has no expiry to recompute
        $this->settings->expects(self::never())->method('forClinic');

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 45,
            practitionerUserId: self::OTHER_PRACT,
        ));

        // Published after the save: AppointmentScheduled (from create) + AppointmentRescheduled + AppointmentPractitionerAssigneeChanged
        self::assertCount(3, $this->events->events);
        self::assertSame([], $appointment->recordedDomainEvents());
        // The day it leaves and the day it takes (P1)
        self::assertSame([self::PRACTITIONER . '|2026-04-11', self::OTHER_PRACT . '|2026-04-12'], $this->dayLock->locked);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMoveIntoABlockOfARefusingTypeIsRefused(): void
    {
        $this->givenAMoveTo(new PlanningBlockReadModel('b', PlanningBlockType::LEAVE, 0, 0, false, 240));
        $this->repository->expects(self::never())->method('save');

        $this->expectException(BlockRefusesAppointmentsException::class);
        $this->expectExceptionMessage('Cannot schedule appointment in a leave block.');

        $this->moveTo('2026-04-12 14:00:00');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMoveIntoAFullBlockIsRefusedAndTheMovedBookingIsNotCounted(): void
    {
        // 1 per hour on a 2-hour block, 2 other bookings in it
        $this->givenAMoveTo(new PlanningBlockReadModel('b', PlanningBlockType::CONSULTATION, 1, 2, true, 120));
        $this->repository->expects(self::never())->method('save');

        try {
            $this->moveTo('2026-04-12 14:00:00');
            self::fail('A full block should refuse the move.');
        } catch (SlotTakenException $e) {
            self::assertTrue($e->isCapacity());
        }
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMoveIntoABlockWithRoomOrOutsideAnyBlockIsAllowed(): void
    {
        $this->givenAMoveTo(new PlanningBlockReadModel('b', PlanningBlockType::CONSULTATION, 1, 1, true, 120));
        $this->repository->expects(self::once())->method('save');

        $this->moveTo('2026-04-12 14:00:00');
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRewritesMotifAndNoteWhenAsked(): void
    {
        $appointment = $this->createFutureAppointment();

        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlapLocked')->willReturn(false);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-11 09:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
            changeDetails: true,
            reason: 'Vaccin',
            notes: 'Rappel annuel',
        ));

        self::assertSame('Vaccin', $appointment->reason());
        self::assertSame('Rappel annuel', $appointment->notes());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsConflict(): void
    {
        $this->expectException(SlotTakenException::class);
        $this->expectExceptionMessage('overlapping');

        $appointment = $this->createFutureAppointment();
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlapLocked')->willReturn(true);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsIneligiblePractitioner(): void
    {
        $this->expectException(PractitionerNotBookableException::class);
        $this->expectExceptionMessage('not eligible');

        $appointment = $this->createFutureAppointment();
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(false);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsCancelledAppointment(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reschedule a terminated appointment.');

        $appointment = $this->createFutureAppointment();
        $appointment->cancel(CancellationActor::CLINIC);
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlapLocked')->willReturn(false);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsNoShowAppointment(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot reschedule a terminated appointment.');

        $appointment = $this->createFutureAppointment();
        $appointment->markNoShow();
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlapLocked')->willReturn(false);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSameTimeSlotAndPractitionerIsNoOp(): void
    {
        $appointment = $this->createFutureAppointment();
        $pulled      = $appointment->pullDomainEvents();
        unset($pulled);

        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlapLocked')->willReturn(false);
        $this->repository->expects(self::once())->method('save');

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-11 09:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));

        // No domain events recorded (both reschedule and changePractitioner are no-ops)
        self::assertCount(0, $appointment->recordedDomainEvents());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsWrongClinic(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Rendez-vous introuvable.');

        $appointment = $this->createFutureAppointment();
        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));

        ($this->handler)(new RescheduleAppointment(
            clinicId: '99999999-9999-9999-9999-999999999999',
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRejectsUnknownAppointment(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Rendez-vous introuvable.');

        $this->repository->method('findById')->willReturn(null);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-12 14:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    /**
     * AC 142: a pending request moved to a later day expires by its new
     * start, min(createdAt + 48 h, newStart − 2 h).
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testReschedulingAPendingRequestRecomputesItsExpiry(): void
    {
        $appointment = $this->createPendingAppointment();

        $this->repository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlapLocked')->willReturn(false);
        $this->settings->expects(self::once())->method('forClinic')
            ->with(self::callback(static fn (ClinicId $id): bool => self::CLINIC_ID === $id->toString()))
            ->willReturn(OnlineBookingSettings::defaults(ClinicId::fromString(self::CLINIC_ID)))
        ;

        // Sooner than 48 h after the request: 2 h before the new start
        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-10 15:00:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));

        self::assertTrue($appointment->status()->isPending());
        self::assertSame('2026-04-10 13:00:00', $appointment->pendingExpiresAt()?->format('Y-m-d H:i:s'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAPendingRequestMovedTooCloseToItsCreationIsRefused(): void
    {
        $appointment = $this->createPendingAppointment();

        $this->repository->method('findById')->willReturn($appointment);
        $this->repository->expects(self::never())->method('save');
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-09 12:30:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlapLocked')->willReturn(false);
        $this->settings->method('forClinic')->willReturn(OnlineBookingSettings::defaults(ClinicId::fromString(self::CLINIC_ID)));

        $this->expectException(AppointmentNotModifiableException::class);

        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable('2026-04-09 13:30:00'),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    private function givenAMoveTo(PlanningBlockReadModel $block): void
    {
        $this->repository->method('findById')->willReturn($this->createFutureAppointment());
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));
        $this->eligibilityChecker->method('isUserEligibleForClinicAt')->willReturn(true);
        $this->conflictChecker->method('hasOverlapLocked')->willReturn(false);
        // 14:00 UTC is 16:00 in Paris; the moved booking is left out of the count
        $this->blockFinder->expects(self::once())->method('findActiveBlockFor')
            ->with(
                self::anything(),
                self::anything(),
                '2026-04-12',
                '16:00',
                '16:30',
                self::callback(static fn (?AppointmentId $id): bool => null !== $id && self::APPOINTMENT_ID === $id->toString()),
            )
            ->willReturn($block)
        ;
    }

    private function moveTo(string $startsAtUtc): void
    {
        ($this->handler)(new RescheduleAppointment(
            clinicId: self::CLINIC_ID,
            appointmentId: self::APPOINTMENT_ID,
            startsAtUtc: new \DateTimeImmutable($startsAtUtc),
            durationMinutes: 30,
            practitionerUserId: self::PRACTITIONER,
        ));
    }

    private function createPendingAppointment(): Appointment
    {
        return Appointment::request(
            id: AppointmentId::fromString(self::APPOINTMENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::PRACTITIONER)),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-12 09:00:00'), 30),
            reason: 'Vaccination',
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
            ownerId: null,
            animalId: null,
            bookingMotifId: null,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: new \DateTimeImmutable('2026-04-11 12:00:00'),
        );
    }

    private function createFutureAppointment(): Appointment
    {
        return Appointment::schedule(
            id: AppointmentId::fromString(self::APPOINTMENT_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::PRACTITIONER)),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-11 09:00:00'), 30),
            reason: 'Consultation',
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
