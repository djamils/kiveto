<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\Scheduling\Domain\Booking\BookingPreventiveCareKind;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Context\Scheduling\Domain\ValueObject\AppointmentStatus;
use App\Context\Scheduling\Domain\ValueObject\CancellationActor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Slice 4: an appointment added to the owner's calendar (AC 52).
 * Today is Monday 2 November 2026, 10:00 UTC.
 */
final class AppointmentCalendarTest extends BookingApiTestCase
{
    private string $vet;

    private string $rex;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.93';
        parent::setUp();

        $this->vet = $this->veterinarian('Claire', 'Rousseau');
        $this->rex = $this->pet('Rex');
    }

    public function testAConfirmedAppointmentIsPublishedWithTheUidAndSequenceOfItsInvitation(): void
    {
        $motif = $this->motif('Rappel vaccin', [$this->vet], BookingPreventiveCareKind::VACCINATION);
        $id    = $this->appointment($this->rex, '2026-11-05 09:00:00', motifId: $motif, practitionerId: $this->vet);
        $this->notified($id, 3);

        $response = $this->calendar($id);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=rendez-vous.ics', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $ics = self::unfold((string) $response->getContent());
        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        self::assertStringContainsString("METHOD:PUBLISH\r\n", $ics);
        self::assertStringContainsString("UID:{$id}@kiveto.app\r\nSEQUENCE:2\r\n", $ics);
        self::assertStringContainsString("DTSTART:20261105T090000Z\r\nDTEND:20261105T093000Z\r\n", $ics);
        self::assertStringContainsString("SUMMARY:Rendez-vous de Rex — Clinique des Lilas\r\n", $ics);
        self::assertStringContainsString("DESCRIPTION:Motif : Rappel vaccin\\nVétérinaire : Dr Claire Rousseau\r\n", $ics);
        self::assertStringContainsString("LOCATION:18 rue Lepic\\, 75018 Paris\r\n", $ics);
        self::assertStringContainsString("STATUS:CONFIRMED\r\n", $ics);
        self::assertStringNotContainsString('ATTENDEE', $ics);
        self::assertStringNotContainsString('ORGANIZER', $ics);
        self::assertStringNotContainsString('Motif interne confidentiel', $ics);
        self::assertStringNotContainsString('Peut mordre', $ics);
    }

    public function testAPendingAppointmentIsTentativeAtSequenceZero(): void
    {
        $id = $this->appointment($this->rex, '2026-11-05 09:00:00', AppointmentStatus::PENDING_CLINIC_CONFIRMATION, source: AppointmentSource::PORTAL);

        $ics = self::unfold((string) $this->calendar($id)->getContent());

        self::assertStringContainsString("SEQUENCE:0\r\n", $ics);
        self::assertStringContainsString("STATUS:TENTATIVE\r\n", $ics);
    }

    public function testAWithdrawnTerminalOrAnotherClientsAppointmentIsNotFound(): void
    {
        $ids = [
            $this->appointment($this->rex, '2026-11-05 09:00:00', AppointmentStatus::CANCELLED, cancelledBy: CancellationActor::CLIENT),
            $this->appointment($this->rex, '2026-11-05 10:00:00', AppointmentStatus::DECLINED),
            $this->appointment($this->rex, '2026-10-05 09:00:00', AppointmentStatus::COMPLETED),
            $this->appointment($this->rex, '2026-10-06 09:00:00', AppointmentStatus::NO_SHOW),
            $this->appointment($this->pet('Stranger', self::OTHER_CLIENT), '2026-11-05 09:00:00', clientId: self::OTHER_CLIENT),
            'not-a-uuid',
        ];

        foreach ($ids as $id) {
            self::assertSame('not_found', self::problemType($this->calendar($id), 404), $id);
        }
    }

    public function testTheCalendarIsHiddenFromAClinicWithoutConfirmedLinkAndFromAnonymousCallers(): void
    {
        $id = $this->appointment($this->rex, '2026-11-05 09:00:00');

        self::assertSame('not_found', self::problemType($this->calendar($id, self::OTHER_CLINIC), 404));
        self::assertSame('unauthorized', self::problemType($this->get(self::clinicPath('/appointments/' . $id . '/calendar.ics')), 401));
    }

    protected function phone(): string
    {
        return '+33615000403';
    }

    private function calendar(string $appointmentId, string $clinicId = self::CLINIC): Response
    {
        return $this->authGet(self::clinicPath('/appointments/' . $appointmentId . '/calendar.ics', $clinicId));
    }

    /** RFC 5545 unfolding: a line break followed by a space continues the line. */
    private static function unfold(string $ics): string
    {
        return str_replace("\r\n ", '', $ics);
    }
}
