<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Animal\Domain\ValueObject\PreventiveCareKind;
use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use Symfony\Component\Uid\Uuid;

/**
 * Slice 4: the owner's appointments, listed and one by one (AC 50, 51).
 * Today is Monday 2 November 2026, 11:00 in Paris (10:00 UTC).
 */
final class AppointmentListTest extends BookingApiTestCase
{
    private string $vet;

    private string $vaccination;

    private string $rex;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.92';
        parent::setUp();

        $this->vet         = $this->veterinarian('Claire', 'Rousseau');
        $this->vaccination = $this->motif('Rappel vaccin', [$this->vet], BookingPreventiveCareKind::VACCINATION);
        $this->rex         = $this->pet('Rex');
        $this->enableOnlineBooking();
    }

    public function testTheAppointmentRoutesAreHiddenFromAClinicWithoutConfirmedLink(): void
    {
        $id = $this->appointment($this->rex, '2026-11-05 09:00:00');

        self::assertSame('not_found', self::problemType($this->authGet(self::clinicPath('/appointments', self::OTHER_CLINIC)), 404));
        self::assertSame('not_found', self::problemType($this->authGet(self::clinicPath('/appointments/' . $id, self::OTHER_CLINIC)), 404));
    }

    public function testAnAppointmentHasTheContractShapeWithTheClinicOffset(): void
    {
        $id = $this->appointment($this->rex, '2026-11-05 09:00:00', AppointmentStatus::PENDING_CLINIC_CONFIRMATION, $this->vaccination, $this->vet, source: AppointmentSource::PORTAL);

        self::assertSame(
            [
                'id'                  => $id,
                'startAt'             => '2026-11-05T10:00:00+01:00',
                'endAt'               => '2026-11-05T10:30:00+01:00',
                'pet'                 => ['id' => $this->rex, 'name' => 'Rex'],
                'motif'               => ['id' => $this->vaccination, 'label' => 'Rappel vaccin'],
                'practitioner'        => ['id' => $this->vet, 'displayName' => 'Dr Claire Rousseau'],
                'status'              => 'pending_clinic_confirmation',
                'source'              => 'portal',
                'cancellableUntil'    => '2026-11-04T10:00:00+01:00',
                'questionnaireStatus' => 'not_started',
                'relatedCareItem'     => null,
            ],
            self::json($this->authGet(self::clinicPath('/appointments/' . $id)), 200),
        );
    }

    public function testUpcomingHoldsTheSlotSoonestFirstAndPastIsEveryOtherLatestFirst(): void
    {
        $later     = $this->appointment($this->rex, '2026-11-09 09:00:00');
        $soon      = $this->appointment($this->rex, '2026-11-03 09:00:00', AppointmentStatus::PENDING_CLINIC_CONFIRMATION);
        $petless   = $this->appointment(null, '2026-11-04 09:00:00');
        $cancelled = $this->appointment($this->rex, '2026-11-06 09:00:00', AppointmentStatus::CANCELLED, cancelledBy: CancellationActor::CLIENT);
        $byClinic  = $this->appointment($this->rex, '2026-11-07 09:00:00', AppointmentStatus::CANCELLED);
        $done      = $this->appointment($this->rex, '2026-10-20 09:00:00', AppointmentStatus::COMPLETED);
        $missed    = $this->appointment($this->rex, '2026-10-10 09:00:00', AppointmentStatus::NO_SHOW);
        $declined  = $this->appointment($this->rex, '2026-10-01 09:00:00', AppointmentStatus::DECLINED);
        $started   = $this->appointment($this->rex, '2026-11-02 09:45:00');
        // Another client's appointment for another client's pet, and another clinic's.
        $this->appointment($this->pet('Stranger', self::OTHER_CLIENT), '2026-11-05 09:00:00', clientId: self::OTHER_CLIENT);

        $upcoming = self::json($this->authGet(self::clinicPath('/appointments?scope=upcoming')), 200);
        self::assertSame([$soon, $petless, $later], self::ids($upcoming));
        self::assertNull($upcoming['nextCursor']);
        self::assertSame(self::ids($upcoming), self::ids(self::json($this->authGet(self::clinicPath('/appointments')), 200)), 'upcoming by default');

        $past = self::json($this->authGet(self::clinicPath('/appointments?scope=past')), 200);
        self::assertSame([$byClinic, $cancelled, $started, $done, $missed, $declined], self::ids($past));
        self::assertSame(
            ['cancelled_by_clinic', 'cancelled_by_client', 'confirmed', 'completed', 'no_show', 'declined'],
            array_column(self::rows($past['items']), 'status'),
        );
        self::assertSame([null, null, null, null, null, null], array_column(self::rows($past['items']), 'cancellableUntil'));

        $items = self::rows($upcoming['items']);
        self::assertNull($items[1]['pet'], 'an appointment without animal');
        self::assertSame(['id' => null, 'label' => 'Rendez-vous'], $items[0]['motif'], 'no online motif');
    }

    public function testPagesFollowTheCursorAndTheLimitIsClampedToFifty(): void
    {
        for ($i = 0; $i < 52; ++$i) {
            $this->appointment($this->rex, (new \DateTimeImmutable('2026-11-03 08:00:00'))->modify('+' . $i . ' hours')->format('Y-m-d H:i:s'));
        }

        $first = self::json($this->authGet(self::clinicPath('/appointments?limit=500')), 200);
        self::assertCount(50, self::rows($first['items']));
        self::assertIsString($first['nextCursor']);

        $second = self::json($this->authGet(self::clinicPath('/appointments?limit=500&cursor=' . urlencode($first['nextCursor']))), 200);
        self::assertCount(2, self::rows($second['items']));
        self::assertNull($second['nextCursor']);
        self::assertSame([], array_intersect(self::ids($first), self::ids($second)));

        self::assertCount(20, self::rows(self::json($this->authGet(self::clinicPath('/appointments')), 200)['items']), '20 by default');
    }

    public function testAForgedCursorOrAnUnknownScopeIsRefused(): void
    {
        self::assertSame('cursor_invalid', self::problemType($this->authGet(self::clinicPath('/appointments?cursor=forged')), 400));
        self::assertSame('validation_failed', self::problemType($this->authGet(self::clinicPath('/appointments?scope=all')), 422));
    }

    public function testNoAnswerCarriesTheReasonNorTheNotes(): void
    {
        $id = $this->appointment($this->rex, '2026-11-05 09:00:00');
        $this->appointment($this->rex, '2026-10-05 09:00:00', AppointmentStatus::COMPLETED);

        foreach (['/appointments', '/appointments?scope=past', '/appointments/' . $id] as $path) {
            $body = (string) $this->authGet(self::clinicPath($path))->getContent();
            self::assertStringNotContainsString('Motif interne confidentiel', $body, $path);
            self::assertStringNotContainsString('Peut mordre', $body, $path);
            self::assertStringNotContainsString('"notes"', $body, $path);
            self::assertStringNotContainsString('"reason"', $body, $path);
        }
    }

    public function testTheDetailCarriesTheCareItemOfTheMotifKind(): void
    {
        $this->careAct($this->rex, PreventiveCareKind::VACCINATION, 'CHPPiL', '2025-11-22', '2026-11-22');
        $vaccine = $this->appointment($this->rex, '2026-11-20 09:00:00', motifId: $this->vaccination, practitionerId: $this->vet);
        $consult = $this->appointment($this->rex, '2026-11-21 09:00:00', motifId: $this->motif('Consultation', [$this->vet]), practitionerId: $this->vet);

        self::assertSame(
            ['kind' => 'vaccination', 'label' => 'CHPPiL', 'dueOn' => '2026-11-22', 'status' => 'booked'],
            self::json($this->authGet(self::clinicPath('/appointments/' . $vaccine)), 200)['relatedCareItem'],
        );
        self::assertNull(self::json($this->authGet(self::clinicPath('/appointments/' . $consult)), 200)['relatedCareItem']);
    }

    public function testAnotherClientsOrAnUnknownAppointmentIsNotFound(): void
    {
        $theirs = $this->appointment($this->pet('Stranger', self::OTHER_CLIENT), '2026-11-05 09:00:00', clientId: self::OTHER_CLIENT);

        foreach ([$theirs, Uuid::v7()->toRfc4122(), 'not-a-uuid'] as $id) {
            self::assertSame('not_found', self::problemType($this->authGet(self::clinicPath('/appointments/' . $id)), 404), $id);
        }
    }

    protected function phone(): string
    {
        return '+33615000402';
    }

    /**
     * @param array<string, mixed> $page
     *
     * @return list<mixed>
     */
    private static function ids(array $page): array
    {
        return array_column(self::rows($page['items'] ?? null), 'id');
    }
}
