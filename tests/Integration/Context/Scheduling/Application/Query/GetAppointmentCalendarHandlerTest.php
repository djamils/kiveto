<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Scheduling\Application\Query;

use App\Context\Scheduling\Application\Query\GetAppointmentCalendar\GetAppointmentCalendar;
use App\Context\Scheduling\Application\Query\GetAppointmentCalendar\GetAppointmentCalendarHandler;
use App\Fixtures\Context\Clinic\Factory\ClinicEntityFactory;
use App\Fixtures\Context\Scheduling\Factory\AppointmentEntityFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

/**
 * The Portal `.ics` read by Scheduling: only an appointment of the clinic
 * that holds its slot; the event of one without animal, motif nor printed
 * practitioner still reads.
 */
final class GetAppointmentCalendarHandlerTest extends KernelTestCase
{
    use Factories;

    private const string CLINIC       = '01960000-0000-7000-8000-00000000c101';
    private const string OTHER_CLINIC = '01960000-0000-7000-8000-00000000c102';
    private const string APPOINTMENT  = '01960000-0000-7000-8000-000000000a01';

    protected function setUp(): void
    {
        ClinicEntityFactory::new()->withId(self::CLINIC)->withName('Clinique Montmartre')->create(['timeZone' => 'Europe/Paris']);
        ClinicEntityFactory::new()->withId(self::OTHER_CLINIC)->create(['timeZone' => 'Europe/Paris']);
        AppointmentEntityFactory::new()->withId(self::APPOINTMENT)->withClinicId(self::CLINIC)->withOwnerAndAnimal(null, null)
            ->startingAt(new \DateTimeImmutable('2026-11-05 09:00:00', new \DateTimeZone('UTC')), 45)
            ->withReason('Motif interne confidentiel')
            ->create()
        ;
    }

    public function testAnAppointmentWithoutAnimalMotifNorPrintedPractitionerReads(): void
    {
        $ics = $this->calendar(self::CLINIC, self::APPOINTMENT);

        self::assertIsString($ics);
        self::assertStringContainsString("SEQUENCE:0\r\n", $ics);
        self::assertStringContainsString("DTEND:20261105T094500Z\r\n", $ics);
        self::assertStringContainsString("SUMMARY:Rendez-vous de votre animal — Clinique Montmartre\r\n", $ics);
        self::assertStringNotContainsString('DESCRIPTION', $ics);
        self::assertStringNotContainsString('LOCATION', $ics);
        self::assertStringNotContainsString('confidentiel', $ics);
    }

    public function testAnUnknownMalformedOrOtherClinicAppointmentHasNoCalendar(): void
    {
        self::assertNull($this->calendar(self::CLINIC, '01960000-0000-7000-8000-000000000a99'));
        self::assertNull($this->calendar(self::CLINIC, 'not-a-uuid'));
        self::assertNull($this->calendar(self::OTHER_CLINIC, self::APPOINTMENT));
    }

    private function calendar(string $clinicId, string $appointmentId): ?string
    {
        $handler = self::getContainer()->get(GetAppointmentCalendarHandler::class);
        \assert($handler instanceof GetAppointmentCalendarHandler);

        return $handler(new GetAppointmentCalendar($clinicId, $appointmentId));
    }
}
