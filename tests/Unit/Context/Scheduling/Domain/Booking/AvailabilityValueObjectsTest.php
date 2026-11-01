<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Domain\Booking;

use App\Context\Scheduling\Domain\Booking\BookableInterval;
use App\Context\Scheduling\Domain\Booking\BookingNotOpenException;
use App\Context\Scheduling\Domain\Booking\BusyInterval;
use App\Context\Scheduling\Domain\Booking\InvalidAvailabilityRangeException;
use App\Context\Scheduling\Domain\Booking\LocalTime;
use App\Context\Scheduling\Domain\Booking\OnlineBookingNotFoundException;
use App\Context\Scheduling\Domain\Booking\OpeningWindow;
use PHPUnit\Framework\TestCase;

final class AvailabilityValueObjectsTest extends TestCase
{
    public function testLocalTimeBuildsInstantsFromWallClockMinutes(): void
    {
        $paris = new \DateTimeZone('Europe/Paris');

        self::assertSame(570, LocalTime::minuteOf('09:30'));
        self::assertSame('2026-04-13T09:30:00+02:00', LocalTime::instant('2026-04-13', 570, $paris)->format('c'));
        // 02:30 does not exist on 29 March: a bound moves forward, a start does not exist
        self::assertSame('2026-03-29T03:30:00+02:00', LocalTime::instant('2026-03-29', 150, $paris)->format('c'));
        self::assertNull(LocalTime::existingInstant('2026-03-29', 150, $paris));
        // 02:30 happens twice on 25 October: PHP takes the standard-time one
        self::assertSame('2026-10-25T02:30:00+01:00', LocalTime::existingInstant('2026-10-25', 150, $paris)?->format('c'));
    }

    public function testLocalTimeRefusesAMalformedDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        LocalTime::instant('13/04/2026', 0, new \DateTimeZone('UTC'));
    }

    public function testIntervalsMustEndAfterTheyStartWithinTheirDay(): void
    {
        self::assertSame(90, new BookableInterval('2026-04-13', 540, 630, 4)->lengthMinutes());
        self::assertSame(1439, new OpeningWindow('2026-04-13', 0, 1439)->endMinute);

        $refused = 0;
        $cases   = [
            static fn (): BookableInterval => new BookableInterval('2026-04-13', 600, 600, 0),
            static fn (): BookableInterval => new BookableInterval('2026-04-13', -1, 600, 0),
            static fn (): BookableInterval => new BookableInterval('2026-04-13', 600, 1440, 0),
            static fn (): OpeningWindow => new OpeningWindow('2026-04-13', 700, 600),
            static fn (): OpeningWindow => new OpeningWindow('2026-04-13', -5, 600),
            static fn (): OpeningWindow => new OpeningWindow('2026-04-13', 0, 1440),
            static fn (): BusyInterval => BusyInterval::unavailable(new \DateTimeImmutable('2026-04-13T10:00Z'), new \DateTimeImmutable('2026-04-13T10:00Z')),
        ];

        foreach ($cases as $case) {
            try {
                $case();
            } catch (\InvalidArgumentException) {
                ++$refused;
            }
        }

        self::assertSame(\count($cases), $refused);
    }

    public function testBusyIntervalsExcludeTheirEnd(): void
    {
        $busy = BusyInterval::appointment(new \DateTimeImmutable('2026-04-13T10:00Z'), new \DateTimeImmutable('2026-04-13T10:30Z'));

        self::assertTrue($busy->countsTowardsCapacity);
        self::assertFalse(BusyInterval::unavailable(new \DateTimeImmutable('2026-04-13T10:00Z'), new \DateTimeImmutable('2026-04-13T10:30Z'))->countsTowardsCapacity);
        self::assertTrue($busy->overlaps(new \DateTimeImmutable('2026-04-13T10:15Z'), new \DateTimeImmutable('2026-04-13T10:45Z')));
        self::assertFalse($busy->overlaps(new \DateTimeImmutable('2026-04-13T10:30Z'), new \DateTimeImmutable('2026-04-13T11:00Z')));
        self::assertFalse($busy->overlaps(new \DateTimeImmutable('2026-04-13T09:30Z'), new \DateTimeImmutable('2026-04-13T10:00Z')));
    }

    public function testRefusalsCarryWhatTheApiMaps(): void
    {
        self::assertSame(['motif', 'Online booking motif "m-1" not found.'], [OnlineBookingNotFoundException::motif('m-1')->subject, OnlineBookingNotFoundException::motif('m-1')->getMessage()]);
        self::assertSame('practitioner', OnlineBookingNotFoundException::practitioner('u-1')->subject);
        self::assertSame(['clinic-1', 'La réservation en ligne n\'est pas ouverte pour cette clinique.'], [new BookingNotOpenException('clinic-1')->clinicId, new BookingNotOpenException('clinic-1')->getMessage()]);
        self::assertSame(
            [
                ['from', 'La date doit être au format AAAA-MM-JJ.'],
                ['to', 'La date de fin doit être postérieure ou égale à la date de début.'],
                ['to', 'La période demandée ne peut pas dépasser 14 jours.'],
                ['from', 'La date de début ne peut pas être dans le passé.'],
            ],
            array_map(
                static fn (InvalidAvailabilityRangeException $e): array => [$e->field, $e->getMessage()],
                [InvalidAvailabilityRangeException::malformed('from'), InvalidAvailabilityRangeException::reversed(), InvalidAvailabilityRangeException::tooLong(14), InvalidAvailabilityRangeException::inThePast()],
            ),
        );
    }
}
