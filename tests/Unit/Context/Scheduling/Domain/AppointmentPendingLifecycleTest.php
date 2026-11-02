<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain;

use App\Context\Scheduling\Domain\Appointment;
use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentCompleted;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Event\AppointmentRequested;
use App\Context\Scheduling\Domain\Event\AppointmentRescheduled;
use App\Context\Scheduling\Domain\Event\AppointmentServiceStarted;
use App\Context\Scheduling\Domain\Exception\AppointmentNotModifiableException;
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
use PHPUnit\Framework\TestCase;

/**
 * An online request waiting for the clinic (S4a, D18): request, confirm,
 * decline, cancel by either side, and what a pending request refuses.
 */
final class AppointmentPendingLifecycleTest extends TestCase
{
    private const string ID     = '01234567-89ab-cdef-0123-456789abcdef';
    private const string CLINIC = '11111111-1111-1111-1111-111111111111';
    private const string VET    = '44444444-4444-4444-4444-444444444444';
    private const string MOTIF  = '55555555-5555-5555-5555-555555555555';

    public function testARequestIsPendingAndRecordsAppointmentRequested(): void
    {
        $appointment = $this->request();

        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $appointment->status());
        self::assertSame(AppointmentSource::PORTAL, $appointment->source());
        self::assertSame(self::MOTIF, $appointment->bookingMotifId());
        self::assertSame('2026-02-01 07:00:00', $appointment->pendingExpiresAt()?->format('Y-m-d H:i:s'));
        self::assertSame(['owner-1', 'animal-1', null], [$appointment->ownerId(), $appointment->animalId(), $appointment->linkedAdmissionId()]);
        self::assertSame(['Vaccination', null], [$appointment->reason(), $appointment->notes()]);
        self::assertSame('2026-01-30 12:00:00', $appointment->createdAt()->format('Y-m-d H:i:s'));
        self::assertNull($appointment->cancelledBy());
        self::assertNull($appointment->cancellationReason());
        self::assertFalse($appointment->confirmedSilently());
        self::assertNull($appointment->serviceStartedAt());

        $events = $appointment->recordedDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentRequested::class, $events[0]);
        self::assertSame([
            'appointmentId'      => self::ID,
            'clinicId'           => self::CLINIC,
            'linkedAdmissionId'  => null,
            'practitionerUserId' => self::VET,
            'startsAtUtc'        => '2026-02-01T09:00:00+00:00',
            'durationMinutes'    => 20,
            'reason'             => 'Vaccination',
            'notes'              => null,
            'ownerId'            => 'owner-1',
            'animalId'           => 'animal-1',
            'bookingMotifId'     => self::MOTIF,
            'source'             => 'portal',
        ], $events[0]->payload());
    }

    public function testARequestMustExpireAfterItIsMade(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->request(expiresAt: '2026-01-30 12:00:00');
    }

    public function testAScheduledAppointmentIsAClinicBookingWithoutPendingState(): void
    {
        $appointment = Appointment::schedule(
            id: AppointmentId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::VET)),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 20),
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-01-30 12:00:00'),
        );

        self::assertSame(AppointmentSource::CLINIC, $appointment->source());
        self::assertNull($appointment->bookingMotifId());
        self::assertNull($appointment->pendingExpiresAt());
        self::assertFalse($appointment->confirmedSilently());
    }

    public function testConfirmMakesItPlanned(): void
    {
        $appointment = $this->request();
        $this->drain($appointment);

        $appointment->confirm();

        self::assertSame(AppointmentStatus::PLANNED, $appointment->status());
        self::assertFalse($appointment->confirmedSilently());
        self::assertNull($appointment->pendingExpiresAt());

        $events = $this->drain($appointment);
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentConfirmed::class, $events[0]);
        self::assertSame([
            'appointmentId'      => self::ID,
            'clinicId'           => self::CLINIC,
            'practitionerUserId' => self::VET,
            'startsAtUtc'        => '2026-02-01T09:00:00+00:00',
            'ownerId'            => 'owner-1',
            'animalId'           => 'animal-1',
            'source'             => 'portal',
            'silent'             => false,
        ], $events[0]->payload());

        // Confirming again changes nothing
        $appointment->confirm();
        self::assertSame([], $this->drain($appointment));
    }

    public function testConfirmingAnythingButAPendingRequestIsRefused(): void
    {
        $appointment = $this->request();
        $appointment->decline(DeclineReason::DECLINED);

        $this->expectException(AppointmentNotModifiableException::class);

        $appointment->confirm();
    }

    public function testDeclineIsTerminalAndRecordedOnce(): void
    {
        $appointment = $this->request();
        $this->drain($appointment);

        $appointment->decline(DeclineReason::EXPIRED);
        $appointment->decline(DeclineReason::EXPIRED);

        self::assertSame(AppointmentStatus::DECLINED, $appointment->status());
        self::assertTrue($appointment->status()->isTerminal());

        $events = $this->drain($appointment);
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentDeclined::class, $events[0]);
        self::assertSame('expired', $events[0]->reason());
        self::assertTrue($events[0]->notifyClient());
        self::assertSame(['owner-1', 'animal-1', 'portal'], [$events[0]->ownerId(), $events[0]->animalId(), $events[0]->source()]);
    }

    public function testDecliningAConfirmedAppointmentIsRefused(): void
    {
        $appointment = $this->request();
        $appointment->confirm();

        $this->expectException(AppointmentNotModifiableException::class);

        $appointment->decline(DeclineReason::DECLINED);
    }

    public function testAClinicCancelOfAPendingRequestIsADecline(): void
    {
        $appointment = $this->request();
        $this->drain($appointment);

        $appointment->cancel(CancellationActor::CLINIC);

        self::assertSame(AppointmentStatus::DECLINED, $appointment->status());
        self::assertNull($appointment->cancelledBy());

        $events = $this->drain($appointment);
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentDeclined::class, $events[0]);
        self::assertSame('clinic_cancelled', $events[0]->reason());

        // The clinic cancelling it again is a no-op
        $appointment->cancel(CancellationActor::CLINIC);
        self::assertSame([], $this->drain($appointment));
    }

    public function testTheDeathFlowDeclinesAPendingRequestSilently(): void
    {
        $appointment = $this->request();
        $this->drain($appointment);

        $appointment->cancel(CancellationActor::CLINIC, notifyClient: false);

        $events = $this->drain($appointment);
        self::assertInstanceOf(AppointmentDeclined::class, $events[0]);
        self::assertFalse($events[0]->notifyClient());
    }

    public function testAClientCancelOfTheirPendingRequestStaysACancellation(): void
    {
        $appointment = $this->request();
        $this->drain($appointment);

        $appointment->cancel(CancellationActor::CLIENT, CancellationReason::SEEN_ELSEWHERE);

        self::assertSame(AppointmentStatus::CANCELLED, $appointment->status());
        self::assertSame(CancellationActor::CLIENT, $appointment->cancelledBy());
        self::assertSame(CancellationReason::SEEN_ELSEWHERE, $appointment->cancellationReason());

        $events = $this->drain($appointment);
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentCancelled::class, $events[0]);
        self::assertSame(['client', 'seen_elsewhere', true, 'portal'], [$events[0]->cancelledBy(), $events[0]->cancellationReason(), $events[0]->wasPending(), $events[0]->source()]);
    }

    public function testAClientCannotCancelADeclinedRequest(): void
    {
        $appointment = $this->request();
        $appointment->decline(DeclineReason::DECLINED);

        $this->expectException(\DomainException::class);

        $appointment->cancel(CancellationActor::CLIENT);
    }

    public function testAClinicCancelOfAConfirmedAppointmentRecordsTheActor(): void
    {
        $appointment = $this->request();
        $appointment->confirm();
        $this->drain($appointment);

        $appointment->cancel(CancellationActor::CLINIC);

        self::assertSame(AppointmentStatus::CANCELLED, $appointment->status());
        self::assertSame(CancellationActor::CLINIC, $appointment->cancelledBy());
        self::assertNull($appointment->cancellationReason());

        $events = $this->drain($appointment);
        self::assertInstanceOf(AppointmentCancelled::class, $events[0]);
        self::assertFalse($events[0]->wasPending());
    }

    /** AC 141 */
    public function testAPendingRequestCannotBeMarkedNoShow(): void
    {
        $appointment = $this->request();

        try {
            $appointment->markNoShow();
            self::fail('A pending request was marked as no-show.');
        } catch (AppointmentNotModifiableException $e) {
            self::assertSame('Un rendez-vous à confirmer ne peut pas être marqué « Non venu ».', $e->getMessage());
        }

        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $appointment->status());
    }

    /** AC 140: the client is on site — confirmed silently, without any message. */
    public function testASilentConfirmationThenACompletion(): void
    {
        $appointment = $this->request();
        $this->drain($appointment);

        $appointment->confirm(silent: true);
        $appointment->complete();

        self::assertSame(AppointmentStatus::COMPLETED, $appointment->status());
        self::assertTrue($appointment->confirmedSilently());

        $events = $this->drain($appointment);
        self::assertCount(2, $events);
        self::assertInstanceOf(AppointmentConfirmed::class, $events[0]);
        self::assertTrue($events[0]->silent());
        self::assertInstanceOf(AppointmentCompleted::class, $events[1]);
    }

    /** The one path of a silent confirmation is SilentPendingConfirmation (under the lock). */
    public function testAPendingRequestIsNeitherCompletedNorServedBeforeItsConfirmation(): void
    {
        $appointment = $this->request();
        $this->drain($appointment);

        $attempts = [
            'Un rendez-vous à confirmer doit être confirmé avant d’être honoré.'      => static fn () => $appointment->complete(),
            'Un rendez-vous à confirmer doit être confirmé avant sa prise en charge.' => static fn () => $appointment->startService(new \DateTimeImmutable('2026-02-01 09:02:00')),
        ];

        foreach ($attempts as $message => $attempt) {
            try {
                $attempt();
                self::fail('A pending request went on without its confirmation.');
            } catch (AppointmentNotModifiableException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }

        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $appointment->status());
        self::assertSame([], $this->drain($appointment));
    }

    /** AC 140: a consultation started from it, once confirmed silently. */
    public function testStartingServiceOnASilentlyConfirmedRequest(): void
    {
        $appointment = $this->request();
        $appointment->confirm(silent: true);
        $this->drain($appointment);

        $appointment->startService(new \DateTimeImmutable('2026-02-01 09:02:00'));

        self::assertSame(AppointmentStatus::PLANNED, $appointment->status());
        self::assertTrue($appointment->confirmedSilently());
        $events = $this->drain($appointment);
        self::assertCount(1, $events);
        self::assertInstanceOf(AppointmentServiceStarted::class, $events[0]);
    }

    /** AC 142 */
    public function testReschedulingAPendingRequestTakesItsNewExpiry(): void
    {
        $appointment = $this->request();
        $this->drain($appointment);

        $appointment->reschedule(new TimeSlot(new \DateTimeImmutable('2026-02-03 10:00:00'), 20), new \DateTimeImmutable('2026-02-01 12:00:00'));

        self::assertSame(AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $appointment->status());
        self::assertSame('2026-02-01 12:00:00', $appointment->pendingExpiresAt()?->format('Y-m-d H:i:s'));
        self::assertInstanceOf(AppointmentRescheduled::class, $this->drain($appointment)[0]);
    }

    public function testReschedulingAPendingRequestNeedsItsNewExpiry(): void
    {
        $appointment = $this->request();

        $this->expectException(\InvalidArgumentException::class);

        $appointment->reschedule(new TimeSlot(new \DateTimeImmutable('2026-02-03 10:00:00'), 20));
    }

    public function testAPendingRequestCannotBeMovedSoItWouldExpireAtOnce(): void
    {
        $appointment = $this->request();

        $this->expectException(AppointmentNotModifiableException::class);

        $appointment->reschedule(new TimeSlot(new \DateTimeImmutable('2026-01-30 13:00:00'), 20), new \DateTimeImmutable('2026-01-30 11:00:00'));
    }

    /** S4A-03: requested long ago, its new expiry is after the request but already past */
    public function testAPendingRequestCannotBeMovedSoItsNewExpiryIsAlreadyPast(): void
    {
        $appointment = $this->request();
        $expiresAt   = new \DateTimeImmutable('2026-01-31 08:00:00');

        try {
            $appointment->reschedule(new TimeSlot(new \DateTimeImmutable('2026-01-31 10:00:00'), 20), $expiresAt, new \DateTimeImmutable('2026-01-31 08:00:00'));
            self::fail('The request would expire at once.');
        } catch (AppointmentNotModifiableException $e) {
            self::assertSame('Ce créneau est trop proche pour une demande à confirmer : confirmez-la d’abord.', $e->getMessage());
        }

        // A minute earlier, it still has a minute to be confirmed
        $appointment->reschedule(new TimeSlot(new \DateTimeImmutable('2026-01-31 10:00:00'), 20), $expiresAt, new \DateTimeImmutable('2026-01-31 07:59:00'));
        self::assertSame('2026-01-31 08:00:00', $appointment->pendingExpiresAt()?->format('Y-m-d H:i:s'));
    }

    public function testReschedulingAPendingRequestToTheSameSlotKeepsItsExpiry(): void
    {
        $appointment = $this->request();
        $this->drain($appointment);

        $appointment->reschedule(new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 20));

        self::assertSame('2026-02-01 07:00:00', $appointment->pendingExpiresAt()?->format('Y-m-d H:i:s'));
        self::assertSame([], $this->drain($appointment));
    }

    public function testReconstituteRestoresTheBookingState(): void
    {
        $appointment = Appointment::reconstitute(
            id: AppointmentId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::VET)),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 20),
            status: AppointmentStatus::CANCELLED,
            reason: null,
            notes: null,
            createdAt: new \DateTimeImmutable('2026-01-30 12:00:00'),
            serviceStartedAt: null,
            source: AppointmentSource::PORTAL,
            bookingMotifId: self::MOTIF,
            cancelledBy: CancellationActor::CLIENT,
            cancellationReason: CancellationReason::OTHER,
            pendingExpiresAt: new \DateTimeImmutable('2026-02-01 07:00:00'),
            confirmedSilently: true,
        );

        self::assertSame(AppointmentSource::PORTAL, $appointment->source());
        self::assertSame(self::MOTIF, $appointment->bookingMotifId());
        self::assertSame(CancellationActor::CLIENT, $appointment->cancelledBy());
        self::assertSame(CancellationReason::OTHER, $appointment->cancellationReason());
        self::assertSame('2026-02-01 07:00:00', $appointment->pendingExpiresAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($appointment->confirmedSilently());
        self::assertSame([], $appointment->recordedDomainEvents());
    }

    private function request(string $expiresAt = '2026-02-01 07:00:00'): Appointment
    {
        return Appointment::request(
            id: AppointmentId::fromString(self::ID),
            clinicId: ClinicId::fromString(self::CLINIC),
            practitionerAssignee: new PractitionerAssignee(UserId::fromString(self::VET)),
            timeSlot: new TimeSlot(new \DateTimeImmutable('2026-02-01 09:00:00'), 20),
            reason: 'Vaccination',
            notes: null,
            createdAt: new \DateTimeImmutable('2026-01-30 12:00:00'),
            ownerId: 'owner-1',
            animalId: 'animal-1',
            bookingMotifId: self::MOTIF,
            source: AppointmentSource::PORTAL,
            pendingExpiresAt: new \DateTimeImmutable($expiresAt),
        );
    }

    /**
     * @return list<object>
     */
    private function drain(Appointment $appointment): array
    {
        return $appointment->pullDomainEvents();
    }
}
