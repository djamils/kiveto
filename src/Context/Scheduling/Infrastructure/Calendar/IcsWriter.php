<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Calendar;

use App\Context\Scheduling\Application\Port\CalendarInvitation;
use App\Context\Scheduling\Application\Port\CalendarInvitationWriterInterface;

/**
 * A minimal RFC 5545 / RFC 5546 (iTIP) writer for appointment e-mails (D4):
 * `METHOD:REQUEST` for an invitation or its update — same UID, growing
 * SEQUENCE, so calendars replace the event — and `METHOD:CANCEL` for a
 * cancellation, always with the ORGANIZER iTIP requires (RFC 5546; the
 * attachment's content type repeats the method). Times in UTC (no VTIMEZONE
 * needed); CRLF line breaks; lines folded at 75 octets without splitting a
 * UTF-8 character; TEXT values escaped.
 */
final class IcsWriter implements CalendarInvitationWriterInterface
{
    private const string CRLF       = "\r\n";
    private const int    LINE_LIMIT = 75;

    public function write(CalendarInvitation $invitation): string
    {
        $end = $invitation->startsAtUtc->modify(\sprintf('+%d minutes', $invitation->durationMinutes));

        $lines = [
            'BEGIN:VCALENDAR',
            'PRODID:-//Kiveto//Agenda//FR',
            'VERSION:2.0',
            'CALSCALE:GREGORIAN',
            'METHOD:' . ($invitation->cancelled ? 'CANCEL' : 'REQUEST'),
            'BEGIN:VEVENT',
            'UID:' . $invitation->uid,
            'SEQUENCE:' . $invitation->sequence,
            'DTSTAMP:' . self::time($invitation->stampedAt),
            'DTSTART:' . self::time($invitation->startsAtUtc),
            'DTEND:' . self::time($end),
            'SUMMARY:' . self::text($invitation->summary),
            'DESCRIPTION:' . self::text($invitation->description),
        ];

        if (null !== $invitation->location) {
            $lines[] = 'LOCATION:' . self::text($invitation->location);
        }

        $lines[] = 'ORGANIZER;CN=' . self::name($invitation->organizerName) . ':mailto:' . $invitation->organizerEmail;
        $lines[] = 'ATTENDEE;CN=' . self::name($invitation->attendeeName) . ';ROLE=REQ-PARTICIPANT;PARTSTAT=NEEDS-ACTION;RSVP=FALSE:mailto:' . $invitation->attendeeEmail;
        $lines[] = 'STATUS:' . ($invitation->cancelled ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'TRANSP:OPAQUE';
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode(self::CRLF, array_map(self::fold(...), $lines)) . self::CRLF;
    }

    private static function time(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /** A TEXT value: backslash, semicolon, comma and line breaks escaped. */
    private static function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', '\n'], $value);
    }

    /** A quoted parameter value (CN): double quotes cannot appear in it. */
    private static function name(string $value): string
    {
        return '"' . str_replace(['"', "\r", "\n"], ["'", ' ', ' '], $value) . '"';
    }

    /** Lines longer than 75 octets continue on lines starting with a space. */
    private static function fold(string $line): string
    {
        $folded = '';
        $length = 0;

        foreach (mb_str_split($line) as $character) {
            $bytes = \strlen($character);

            if ($length + $bytes > self::LINE_LIMIT) {
                $folded .= self::CRLF . ' ';
                $length = 1;
            }

            $folded .= $character;
            $length += $bytes;
        }

        return $folded;
    }
}
