<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Application\Command\CancelAppointment;

use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointment;
use App\Context\Scheduling\Application\Command\CancelAppointment\CancelAppointmentHandler;
use App\Context\Scheduling\Application\Service\PendingAppointmentLoader;
use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Exception\AppointmentAlreadyHandledException;
use App\Context\Scheduling\Domain\Repository\AppointmentRepositoryInterface;
use App\Context\Scheduling\Domain\ValueObject\AppointmentId;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\CancellationReason;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\DeclineReason;
use App\Context\Scheduling\Domain\ValueObject\PractitionerAssignee;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Unit\Context\Scheduling\Support\CapturingEventBus;
use App\Tests\Unit\Context\Scheduling\Support\ParisTimezone;
use App\Tests\Unit\Context\Scheduling\Support\RecordingDayLock;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CancelAppointmentHandlerTest extends TestCase
{
    private const string CLINIC = '22222222-2222-2222-2222-222222222222';

    private AppointmentRepositoryInterface&MockObject $appointmentRepository;
    private ClockInterface&MockObject $clock;
    private CancelAppointmentHandler $handler;
    private CapturingEventBus $events;
    private RecordingDayLock $dayLock;

    protected function setUp(): void
    {
        $this->appointmentRepository = $this->createMock(AppointmentRepositoryInterface::class);
        $this->clock                 = $this->createMock(ClockInterface::class);

        $this->events  = new CapturingEventBus();
        $this->dayLock = new RecordingDayLock();
        $this->handler = new CancelAppointmentHandler(
            $this->appointmentRepository,
            new PendingAppointmentLoader($this->appointmentRepository, new ParisTimezone(), $this->dayLock),
            $this->clock,
            new DomainEventPublisher($this->events),
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCancelAppointmentSucceeds(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);

        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($appointment);
        $this->appointmentRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static fn (Appointment $a): bool => AppointmentStatus::CANCELLED === $a->status()))
        ;
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:55:00'));

        ($this->handler)(new CancelAppointment($appointmentId->toString(), self::CLINIC));

        // A planned appointment needs no practitioner-day lock
        self::assertSame([], $this->dayLock->locked);

        // Published after the save: the scheduling subscribers react to it.
        $cancelled = array_values(array_filter($this->events->events, static fn (object $e): bool => $e instanceof AppointmentCancelled));
        self::assertCount(1, $cancelled);
        self::assertTrue($cancelled[0]->notifyClient());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTheDeathOfTheAnimalCancelsWithoutTellingTheClient(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');

        $this->appointmentRepository->method('findById')->willReturn($this->makeAppointment($appointmentId));
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:55:00'));

        ($this->handler)(new CancelAppointment($appointmentId->toString(), self::CLINIC, notifyClient: false));

        $cancelled = array_values(array_filter($this->events->events, static fn (object $e): bool => $e instanceof AppointmentCancelled));
        self::assertCount(1, $cancelled);
        self::assertFalse($cancelled[0]->notifyClient());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAClientCancelsWithAReason(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);

        $this->appointmentRepository->method('findById')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 08:00:00'));

        ($this->handler)(new CancelAppointment($appointmentId->toString(), self::CLINIC, cancelledBy: 'client', cancellationReason: 'no_longer_needed'));

        self::assertSame(AppointmentStatus::CANCELLED, $appointment->status());
        self::assertSame(CancellationActor::CLIENT, $appointment->cancelledBy());
        self::assertSame(CancellationReason::NO_LONGER_NEEDED, $appointment->cancellationReason());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTheClinicCancellingAPendingRequestDeclinesItUnderTheLocks(): void
    {
        $appointment = $this->makePending();

        $this->appointmentRepository->method('findById')->willReturn($appointment);
        $this->appointmentRepository->expects(self::once())->method('findByIdForUpdate')->willReturn($appointment);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 06:00:00'));

        ($this->handler)(new CancelAppointment($appointment->id()->toString(), self::CLINIC));

        self::assertSame(AppointmentStatus::DECLINED, $appointment->status());
        $declined = array_values(array_filter($this->events->events, static fn (object $e): bool => $e instanceof AppointmentDeclined));
        self::assertCount(1, $declined);
        self::assertSame('clinic_cancelled', $declined[0]->reason());
        // 11:00 Paris on 10 April: that day of the practitioner, before the row read for update
        self::assertSame(['44444444-4444-4444-4444-444444444444|2026-04-10'], $this->dayLock->locked);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAClinicCancelOfARequestConfirmedMeanwhileLosesTheRace(): void
    {
        $seen    = $this->makePending();
        $current = $this->makePending();
        $current->confirm();
        $_ = $current->pullDomainEvents();

        $this->appointmentRepository->method('findById')->willReturn($seen);
        $this->appointmentRepository->method('findByIdForUpdate')->willReturn($current);
        $this->appointmentRepository->expects(self::never())->method('save');

        try {
            ($this->handler)(new CancelAppointment($seen->id()->toString(), self::CLINIC));
            self::fail('The confirmation won: the decline must be refused.');
        } catch (AppointmentAlreadyHandledException $e) {
            self::assertSame('Ce RDV a déjà été traité.', $e->getMessage());
        }

        self::assertSame(AppointmentStatus::PLANNED, $current->status());
        self::assertSame([], $this->events->events);
    }

    /**
     * FU-04: declined (or expired) meanwhile, the request is already what the
     * clinic's cancel wanted: nothing to do, no « déjà traité ».
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testAClinicCancelOfARequestDeclinedMeanwhileIsANoOp(): void
    {
        $seen    = $this->makePending();
        $current = $this->makePending();
        $current->decline(DeclineReason::EXPIRED);
        $_ = $current->pullDomainEvents();

        $this->appointmentRepository->method('findById')->willReturn($seen);
        $this->appointmentRepository->method('findByIdForUpdate')->willReturn($current);
        $this->appointmentRepository->expects(self::never())->method('save');

        ($this->handler)(new CancelAppointment($seen->id()->toString(), self::CLINIC, notifyClient: false));

        self::assertSame(AppointmentStatus::DECLINED, $current->status());
        self::assertSame([], $this->events->events);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAClientCancelOfARequestConfirmedMeanwhileCancelsIt(): void
    {
        $seen    = $this->makePending();
        $current = $this->makePending();
        $current->confirm();
        $_ = $current->pullDomainEvents();

        $this->appointmentRepository->method('findById')->willReturn($seen);
        $this->appointmentRepository->method('findByIdForUpdate')->willReturn($current);
        $this->clock->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 06:00:00'));

        ($this->handler)(new CancelAppointment($seen->id()->toString(), self::CLINIC, cancelledBy: 'client'));

        self::assertSame(AppointmentStatus::CANCELLED, $current->status());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAPendingRequestGoneUnderTheLockIsReportedAsMissing(): void
    {
        $this->appointmentRepository->method('findById')->willReturn($this->makePending());
        $this->appointmentRepository->method('findByIdForUpdate')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "11111111-1111-1111-1111-111111111111" does not exist.');

        ($this->handler)(new CancelAppointment('11111111-1111-1111-1111-111111111111', self::CLINIC));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAppointmentNotFound(): void
    {
        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "11111111-1111-1111-1111-111111111111" does not exist.');

        ($this->handler)(new CancelAppointment('11111111-1111-1111-1111-111111111111', self::CLINIC));
    }

    public function testAppointmentOfAnotherClinicIsReportedAsMissing(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');

        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($this->makeAppointment($appointmentId));
        $this->appointmentRepository->expects(self::never())->method('save');
        $this->clock->expects(self::never())->method('now');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Appointment with ID "11111111-1111-1111-1111-111111111111" does not exist.');

        ($this->handler)(new CancelAppointment($appointmentId->toString(), '99999999-9999-9999-9999-999999999999'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFailsWhenAppointmentIsPast(): void
    {
        $appointmentId = AppointmentId::fromString('11111111-1111-1111-1111-111111111111');
        $appointment   = $this->makeAppointment($appointmentId);

        $this->appointmentRepository->expects(self::once())->method('findById')->willReturn($appointment);
        $this->appointmentRepository->expects(self::never())->method('save');
        $this->clock->expects(self::once())->method('now')->willReturn(new \DateTimeImmutable('2026-04-10 10:00:00'));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Impossible d\'annuler un rendez-vous passé.');

        ($this->handler)(new CancelAppointment($appointmentId->toString(), self::CLINIC));
    }

    private function makePending(): Appointment
    {
        return Appointment::request(
            id: AppointmentId::fromString('11111111-1111-1111-1111-111111111111'),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00', new \DateTimeZone('UTC')), 30),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00', new \DateTimeZone('UTC')),
            ownerId: null,
            animalId: null,
            bookingMotifId: null,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: new \DateTimeImmutable('2026-04-10 07:00:00', new \DateTimeZone('UTC')),
        );
    }

    private function makeAppointment(AppointmentId $id): Appointment
    {
        return Appointment::schedule(
            id: $id,
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString('44444444-4444-4444-4444-444444444444')),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-04-10 09:00:00'), 30),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-04-09 12:00:00'),
        );
    }
}
