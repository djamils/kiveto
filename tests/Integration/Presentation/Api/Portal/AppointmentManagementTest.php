<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use App\Context\Scheduling\Domain\ValueObject\PlanningBlockType;
use App\System\Notification\Application\Command\PostInAppNotification\PostInAppNotification;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 4: the owner cancels and moves their online bookings (AC 48, 49,
 * 152, 153). Today is Monday 2 November 2026, 11:00 in Paris (10:00 UTC);
 * the cancellation window is 24 h.
 */
final class AppointmentManagementTest extends BookingApiTestCase
{
    private string $vet;

    private string $otherVet;

    private string $vaccination;

    private string $rex;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.94';
        parent::setUp();

        $this->vet         = $this->veterinarian('Claire', 'Rousseau');
        $this->otherVet    = $this->veterinarian('Antoine', 'Bernard');
        $this->vaccination = $this->motif('Rappel vaccin', [$this->vet, $this->otherVet], BookingPreventiveCareKind::VACCINATION);
        $this->rex         = $this->pet('Rex');
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-04', '09:00', '12:30');
        $this->block($this->otherVet, PlanningBlockType::CONSULTATION, '2026-11-05', '09:00', '12:30');
        $this->enableOnlineBooking();
    }

    public function testTheOwnerCancelsInsideTheWindowAndTheSlotIsFreed(): void
    {
        // Created 3 days ago, starts in 30 h (Tuesday 17:00 Paris)
        $this->block($this->vet, PlanningBlockType::CONSULTATION, '2026-11-03', '16:00', '18:00');
        $id = $this->online('2026-11-03 16:00:00', createdAtUtc: '2026-10-30 10:00:00');
        self::assertNotContains('2026-11-03T17:00:00+01:00', $this->slots('2026-11-03'));

        $answer = self::json($this->cancel($id, 'unavailable'), 200);

        self::assertSame(['cancelled_by_client', null], [$answer['status'], $answer['cancellableUntil']]);
        self::assertSame(['id' => $this->vet, 'displayName' => 'Dr Claire Rousseau'], $answer['practitioner']);
        self::assertContains('2026-11-03T17:00:00+01:00', $this->slots('2026-11-03'), 'the slot is freed');
        self::assertSame('unavailable', $this->connection()->fetchOne('SELECT cancellation_reason FROM scheduling__appointments WHERE id = UUID_TO_BIN(?)', [$id]));
        self::assertSame(1, $this->queuedSyncs($id), 'the usual cancellation message is on its way');
        self::assertSame([[$this->vet]], $this->bells('ONLINE_BOOKING_CHANGED'));
    }

    public function testCancelAndRescheduleAreClosedInsideTheWindow(): void
    {
        // Created 3 days ago, starts in 20 h
        $id = $this->online('2026-11-03 06:00:00', createdAtUtc: '2026-10-30 10:00:00');

        self::assertSame('cancellation_window_closed', self::problemType($this->cancel($id), 422));
        self::assertSame('cancellation_window_closed', self::problemType($this->reschedule($id, '2026-11-04T10:00:00+01:00'), 422));
    }

    public function testExactlyAtTheEndOfTheWindowTheOwnerMayStillCancel(): void
    {
        // Starts in exactly 24 h: cancellableUntil = now
        $id = $this->online('2026-11-03 10:00:00', createdAtUtc: '2026-10-30 10:00:00');

        self::assertSame('cancelled_by_client', self::json($this->cancel($id), 200)['status']);
    }

    public function testABookingMadeInsideTheWindowCanBeCancelledDuringItsFirstFifteenMinutes(): void
    {
        // Booked now, starts in 2 h: cancellableUntil = createdAt + 15 min
        $first  = $this->online('2026-11-02 12:00:00', createdAtUtc: self::NOW);
        $second = $this->online('2026-11-02 12:30:00', createdAtUtc: self::NOW);

        self::assertSame('2026-11-02T11:15:00+01:00', self::json($this->authGet(self::clinicPath('/appointments/' . $first)), 200)['cancellableUntil']);

        $this->travelTo('2026-11-02 10:15:00');
        self::assertSame('cancelled_by_client', self::json($this->cancel($first), 200)['status']);

        $this->travelTo('2026-11-02 10:16:00');
        self::assertSame('cancellation_window_closed', self::problemType($this->cancel($second), 422));
    }

    public function testCancellingTwiceAnswersTheSameBody(): void
    {
        $id = $this->online('2026-11-04 09:00:00');

        $first = self::json($this->cancel($id, 'seen_elsewhere'), 200);

        self::assertSame($first, self::json($this->cancel($id, 'other'), 200));
        self::assertSame('seen_elsewhere', $this->connection()->fetchOne('SELECT cancellation_reason FROM scheduling__appointments WHERE id = UUID_TO_BIN(?)', [$id]), 'the first reason stays');
        self::assertSame(1, $this->queuedSyncs($id));
    }

    /**
     * @return iterable<string, array{AppointmentStatus, ?CancellationActor}>
     */
    public static function provideAnEndedAppointmentIsNotModifiableCases(): iterable
    {
        yield 'declined' => [AppointmentStatus::DECLINED, null];
        yield 'completed' => [AppointmentStatus::COMPLETED, null];
        yield 'no-show' => [AppointmentStatus::NO_SHOW, null];
        yield 'cancelled by the clinic' => [AppointmentStatus::CANCELLED, CancellationActor::CLINIC];
    }

    #[DataProvider('provideAnEndedAppointmentIsNotModifiableCases')]
    public function testAnEndedAppointmentIsNotModifiable(AppointmentStatus $status, ?CancellationActor $actor): void
    {
        $id = $this->online('2026-11-04 09:00:00', $status, cancelledBy: $actor);

        self::assertSame('appointment_not_modifiable', self::problemType($this->cancel($id), 422));
        self::assertSame('appointment_not_modifiable', self::problemType($this->reschedule($id, '2026-11-04T11:00:00+01:00'), 422));
    }

    public function testTheOwnerMovesTheBookingToAnotherPractitionerOfTheMotifKeepingItsStatus(): void
    {
        $id = $this->online('2026-11-04 08:00:00', AppointmentStatus::PENDING_CLINIC_CONFIRMATION, createdAtUtc: '2026-11-02 09:00:00');

        $answer = self::json($this->reschedule($id, '2026-11-05T10:00:00+01:00', $this->otherVet), 200);

        self::assertSame(
            ['2026-11-05T10:00:00+01:00', '2026-11-05T10:30:00+01:00', ['id' => $this->otherVet, 'displayName' => 'Dr Antoine Bernard'], 'pending_clinic_confirmation'],
            [$answer['startAt'], $answer['endAt'], $answer['practitioner'], $answer['status']],
        );
        self::assertContains('2026-11-04T09:00:00+01:00', $this->slots('2026-11-04'), 'the old slot is freed');
        self::assertSame([[$this->vet, $this->otherVet]], $this->bells('ONLINE_BOOKING_CHANGED'), 'both practitioners are told');
    }

    public function testMovingToTheSameSlotChangesNothing(): void
    {
        $id     = $this->online('2026-11-04 08:00:00');
        $before = self::json($this->authGet(self::clinicPath('/appointments/' . $id)), 200);

        self::assertSame($before, self::json($this->reschedule($id, '2026-11-04T09:00:00+01:00'), 200));
        self::assertSame([], $this->bells('ONLINE_BOOKING_CHANGED'));
    }

    public function testMovingOntoATakenSlotIsRefused(): void
    {
        $id = $this->online('2026-11-04 08:00:00');
        $this->appointment($this->pet('Nala'), '2026-11-05 09:00:00', practitionerId: $this->otherVet);

        self::assertSame('slot_taken', self::problemType($this->reschedule($id, '2026-11-05T10:00:00+01:00', $this->otherVet), 409));
        self::assertSame('slot_taken', self::problemType($this->reschedule($id, '2026-11-05T10:05:00+01:00', $this->otherVet), 409), 'off the grid');
    }

    public function testMovingToAPractitionerNotBookableForTheMotifIsNotFound(): void
    {
        $id      = $this->online('2026-11-04 08:00:00');
        $outside = $this->veterinarian('Paul', 'Martin');

        self::assertSame('not_found', self::problemType($this->reschedule($id, '2026-11-05T10:00:00+01:00', $outside), 404));
        self::assertSame('not_found', self::problemType($this->reschedule($id, '2026-11-05T10:00:00+01:00', Uuid::v7()->toRfc4122()), 404));
    }

    public function testAnAppointmentTakenByTheClinicCannotBeMovedOnline(): void
    {
        $id = $this->appointment($this->rex, '2026-11-04 08:00:00', practitionerId: $this->vet);

        self::assertSame('booking_not_open', self::problemType($this->reschedule($id, '2026-11-04T10:00:00+01:00'), 422));
    }

    public function testAnotherClientsOrAnUnknownAppointmentIsNotFound(): void
    {
        $stranger = $this->online('2026-11-04 08:00:00', petId: $this->pet('Stranger', self::OTHER_CLIENT), clientId: self::OTHER_CLIENT);

        foreach ([$stranger, Uuid::v7()->toRfc4122(), 'not-an-id'] as $id) {
            self::assertSame('not_found', self::problemType($this->cancel($id), 404), $id);
            self::assertSame('not_found', self::problemType($this->reschedule($id, '2026-11-04T10:00:00+01:00'), 404), $id);
        }
    }

    public function testTheManagementRoutesAreHiddenFromAClinicWithoutConfirmedLink(): void
    {
        $id = $this->online('2026-11-04 08:00:00');

        self::assertSame('not_found', self::problemType($this->authPost(self::clinicPath('/appointments/' . $id . '/cancel', self::OTHER_CLINIC), ['reason' => 'other']), 404));
        self::assertSame('not_found', self::problemType($this->authPost(self::clinicPath('/appointments/' . $id . '/reschedule', self::OTHER_CLINIC), ['startAt' => '2026-11-04T10:00:00+01:00', 'practitionerId' => $this->vet]), 404));
    }

    public function testAMalformedRequestIsRefused(): void
    {
        $id = $this->online('2026-11-04 08:00:00');

        $problem = self::json($this->cancel($id, 'bored'), 422);
        self::assertSame(['validation_failed', 'reason'], [$problem['type'], self::firstViolationPath($problem)]);
        $problem = self::json($this->authPost(self::clinicPath('/appointments/' . $id . '/cancel'), []), 422);
        self::assertSame(['validation_failed', 'reason'], [$problem['type'], self::firstViolationPath($problem)]);

        $problem = self::json($this->reschedule($id, '04/11/2026 10:00'), 422);
        self::assertSame(['validation_failed', 'startAt'], [$problem['type'], self::firstViolationPath($problem)]);
        $problem = self::json($this->reschedule($id, '2026-11-04T10:00:00+01:00', 'claire'), 422);
        self::assertSame(['validation_failed', 'practitionerId'], [$problem['type'], self::firstViolationPath($problem)]);
    }

    protected function phone(): string
    {
        return '+33615000405';
    }

    /** A confirmed online booking of Rex with the vaccination motif and the veterinarian. */
    private function online(
        string $startsAtUtc,
        AppointmentStatus $status = AppointmentStatus::PLANNED,
        string $createdAtUtc = '2026-10-01 08:00:00',
        ?CancellationActor $cancelledBy = null,
        ?string $petId = null,
        string $clientId = self::CLIENT,
    ): string {
        return $this->appointment(
            $petId ?? $this->rex,
            $startsAtUtc,
            $status,
            $this->vaccination,
            $this->vet,
            $clientId,
            AppointmentSource::PORTAL,
            $cancelledBy,
            $createdAtUtc,
        );
    }

    private function cancel(string $appointmentId, string $reason = 'no_longer_needed'): Response
    {
        return $this->authPost(self::clinicPath('/appointments/' . $appointmentId . '/cancel'), ['reason' => $reason]);
    }

    private function reschedule(string $appointmentId, string $startAt, ?string $practitionerId = null): Response
    {
        return $this->authPost(self::clinicPath('/appointments/' . $appointmentId . '/reschedule'), ['startAt' => $startAt, 'practitionerId' => $practitionerId ?? $this->vet]);
    }

    /**
     * The starts offered on that day for the vaccination motif and the veterinarian.
     *
     * @return list<mixed>
     */
    private function slots(string $date): array
    {
        $days = self::rows(self::json($this->authGet(self::clinicPath(\sprintf(
            '/booking/availability?motifId=%s&practitionerId=%s&from=%s&to=%s',
            $this->vaccination,
            $this->vet,
            $date,
            $date,
        ))), 200)['days'] ?? null);

        return array_column(self::rows($days[0]['slots'] ?? null), 'startAt');
    }

    /**
     * The practitioners of each bell entry of that kind posted so far.
     *
     * @return list<list<string>>
     */
    private function bells(string $kind): array
    {
        return array_values(array_map(
            static fn (PostInAppNotification $bell): array => $bell->userIds,
            array_filter($this->bus()->recorded(PostInAppNotification::class), static fn (PostInAppNotification $bell): bool => $kind === $bell->kind),
        ));
    }

    /** The client-message syncs queued for the appointment. */
    private function queuedSyncs(string $appointmentId): int
    {
        return $this->number('SELECT COUNT(*) FROM shared__messenger_messages WHERE body LIKE ? AND body LIKE ?', ['%SyncAppointmentNotification%', '%' . $appointmentId . '%']);
    }
}
