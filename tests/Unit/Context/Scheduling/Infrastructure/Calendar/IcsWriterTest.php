<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Calendar;

use App\Context\Scheduling\Application\Port\CalendarInvitation;
use App\Context\Scheduling\Infrastructure\Calendar\IcsWriter;
use PHPUnit\Framework\TestCase;

final class IcsWriterTest extends TestCase
{
    public function testAnInvitationIsAnRfc5545Request(): void
    {
        $ics = new IcsWriter()->write($this->invitation(cancelled: false, sequence: 0));

        self::assertSame(
            "BEGIN:VCALENDAR\r\n"
            . "PRODID:-//Kiveto//Agenda//FR\r\n"
            . "VERSION:2.0\r\n"
            . "CALSCALE:GREGORIAN\r\n"
            . "METHOD:REQUEST\r\n"
            . "BEGIN:VEVENT\r\n"
            . "UID:a-1@kiveto.fr\r\n"
            . "SEQUENCE:0\r\n"
            . "DTSTAMP:20261013T084400Z\r\n"
            . "DTSTART:20261022T073000Z\r\n"
            . "DTEND:20261022T080000Z\r\n"
            . "SUMMARY:Rendez-vous de Milo — Clinique Montmartre\r\n"
            . "DESCRIPTION:Motif : Vaccination\\, rappel\; vermifuge\\nVétérinaire : Dr Ro\r\n"
            . " usseau\r\n"
            . "LOCATION:18 rue Lepic\\, 75018 Paris\r\n"
            . "ORGANIZER;CN=\"Clinique 'Montmartre'\":mailto:accueil@clinique-montmartre.fr\r\n"
            . "ATTENDEE;CN=\"Sophie Dubois\";ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP\r\n"
            . " =FALSE:mailto:s.dubois@email.fr\r\n"
            . "STATUS:CONFIRMED\r\n"
            . "TRANSP:OPAQUE\r\n"
            . "END:VEVENT\r\n"
            . "END:VCALENDAR\r\n",
            $ics,
        );

        foreach (explode("\r\n", $ics) as $line) {
            self::assertLessThanOrEqual(75, \strlen($line));
        }
    }

    public function testACancellationKeepsTheUidWithAHigherSequence(): void
    {
        $ics = new IcsWriter()->write($this->invitation(cancelled: true, sequence: 2, location: null));

        self::assertStringContainsString("METHOD:CANCEL\r\n", $ics);
        self::assertStringContainsString("UID:a-1@kiveto.fr\r\nSEQUENCE:2\r\n", $ics);
        self::assertStringContainsString("STATUS:CANCELLED\r\n", $ics);
        self::assertStringNotContainsString('LOCATION', $ics);
        // iTIP: a CANCEL names its organizer.
        self::assertStringContainsString('ORGANIZER;CN="Clinique \'Montmartre\'":mailto:accueil@clinique-montmartre.fr', $ics);
    }

    private function invitation(bool $cancelled, int $sequence, ?string $location = '18 rue Lepic, 75018 Paris'): CalendarInvitation
    {
        return new CalendarInvitation(
            uid: 'a-1@kiveto.fr',
            sequence: $sequence,
            cancelled: $cancelled,
            startsAtUtc: new \DateTimeImmutable('2026-10-22 09:30', new \DateTimeZone('Europe/Paris')),
            durationMinutes: 30,
            summary: 'Rendez-vous de Milo — Clinique Montmartre',
            description: "Motif : Vaccination, rappel; vermifuge\nVétérinaire : Dr Rousseau",
            location: $location,
            organizerName: 'Clinique "Montmartre"',
            organizerEmail: 'accueil@clinique-montmartre.fr',
            attendeeName: 'Sophie Dubois',
            attendeeEmail: 's.dubois@email.fr',
            stampedAt: new \DateTimeImmutable('2026-10-13 08:44', new \DateTimeZone('UTC')),
        );
    }
}
