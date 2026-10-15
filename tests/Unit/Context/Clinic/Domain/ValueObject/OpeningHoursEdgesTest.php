<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\ValueObject\ExceptionalClosure;
use App\Context\Clinic\Domain\ValueObject\OpeningHours;
use App\Context\Clinic\Domain\ValueObject\OpeningSlot;
use App\Context\Clinic\Infrastructure\Persistence\Doctrine\Entity\ClinicEntity;
use PHPUnit\Framework\TestCase;

/**
 * The corners of the week the settings page saves: closed days, rows written
 * by hand, and values compared with themselves.
 */
final class OpeningHoursEdgesTest extends TestCase
{
    public function testAClosedDayIsLeftOutOfTheWeek(): void
    {
        $hours = OpeningHours::create([1 => [OpeningSlot::create('09:00', '12:00')], 2 => []]);

        self::assertSame([1], array_keys($hours->week()));
        self::assertTrue($hours->isClosedOn(2));
    }

    public function testARowWrittenByHandIsReadForWhatItHolds(): void
    {
        $hours = OpeningHours::fromArray([
            'week'     => [1 => 'nope', 2 => ['nope', ['opensAt' => '08:00', 'closesAt' => '12:00']]],
            'closures' => ['nope', ['date' => 12], ['date' => '2026-12-25', 'reason' => 'Noël']],
        ]);

        self::assertTrue($hours->isClosedOn(1));
        self::assertCount(1, $hours->slotsOn(2));
        self::assertCount(1, $hours->closures);
    }

    public function testSlotsAndClosuresCompareByValue(): void
    {
        self::assertTrue(OpeningSlot::create('09:00', '12:00')->equals(OpeningSlot::create(' 09:00', '12:00 ')));
        self::assertFalse(OpeningSlot::create('09:00', '12:00')->equals(OpeningSlot::create('09:00', '12:30')));
        self::assertTrue(ExceptionalClosure::create('2026-12-25', 'Noël')->equals(ExceptionalClosure::create('2026-12-25', ' Noël ')));
        self::assertFalse(ExceptionalClosure::create('2026-12-25', 'Noël')->equals(ExceptionalClosure::create('2026-12-25')));
    }

    public function testANewRowStartsAtVersionOne(): void
    {
        self::assertSame(1, (new ClinicEntity())->getVersion());
    }
}
