<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Domain\ValueObject;

use App\Context\Clinic\Domain\Exception\InvalidOpeningHoursException;
use App\Context\Clinic\Domain\ValueObject\ExceptionalClosure;
use App\Context\Clinic\Domain\ValueObject\OpeningHours;
use App\Context\Clinic\Domain\ValueObject\OpeningSlot;
use PHPUnit\Framework\TestCase;

final class OpeningHoursTest extends TestCase
{
    public function testADayCanHoldTwoSlots(): void
    {
        $hours = OpeningHours::create([
            1 => [OpeningSlot::create('14:00', '19:00'), OpeningSlot::create('09:00', '12:30')],
        ]);

        $slots = $hours->slotsOn(1);

        self::assertCount(2, $slots);
        // Whatever order they were given in, the morning comes first.
        self::assertSame('09:00', $slots[0]->opensAt);
        self::assertSame('14:00', $slots[1]->opensAt);
    }

    public function testADayWithoutSlotsIsClosed(): void
    {
        $hours = OpeningHours::create([1 => [OpeningSlot::create('09:00', '19:00')]]);

        self::assertFalse($hours->isClosedOn(1));
        self::assertTrue($hours->isClosedOn(7));
    }

    public function testASlotThatClosesBeforeItOpensIsRefused(): void
    {
        $this->expectException(InvalidOpeningHoursException::class);

        OpeningSlot::create('18:00', '09:00');
    }

    public function testASlotThatClosesWhenItOpensIsRefused(): void
    {
        $this->expectException(InvalidOpeningHoursException::class);

        OpeningSlot::create('09:00', '09:00');
    }

    public function testAnHourThatIsNotAnHourIsRefused(): void
    {
        $this->expectException(InvalidOpeningHoursException::class);

        OpeningSlot::create('9h', '12:00');
    }

    public function testTwoSlotsOfTheSameDayCannotOverlap(): void
    {
        $this->expectException(InvalidOpeningHoursException::class);

        OpeningHours::create([
            1 => [OpeningSlot::create('09:00', '13:00'), OpeningSlot::create('12:00', '19:00')],
        ]);
    }

    public function testSlotsThatTouchAreAccepted(): void
    {
        $hours = OpeningHours::create([
            1 => [OpeningSlot::create('09:00', '13:00'), OpeningSlot::create('13:00', '19:00')],
        ]);

        self::assertCount(2, $hours->slotsOn(1));
    }

    public function testADayOutsideTheWeekIsRefused(): void
    {
        $this->expectException(InvalidOpeningHoursException::class);

        OpeningHours::create([8 => [OpeningSlot::create('09:00', '12:00')]]);
    }

    public function testAClosureNeedsARealDate(): void
    {
        $this->expectException(InvalidOpeningHoursException::class);

        ExceptionalClosure::create('2026-02-30');
    }

    public function testAClosureKeepsItsReasonAndDropsAnEmptyOne(): void
    {
        self::assertSame('Noël', ExceptionalClosure::create('2026-12-25', 'Noël')->reason);
        self::assertNull(ExceptionalClosure::create('2026-12-25', '   ')->reason);
    }

    public function testAWeekSurvivesTheRoundTripThroughAnArray(): void
    {
        $hours = OpeningHours::create(
            [1 => [OpeningSlot::create('09:00', '12:30')], 6 => [OpeningSlot::create('09:00', '13:00')]],
            [ExceptionalClosure::create('2026-12-25', 'Noël')],
        );

        $rebuilt = OpeningHours::fromArray($hours->toArray());

        self::assertTrue($rebuilt->equals($hours));
        self::assertSame('Noël', $rebuilt->closures[0]->reason);
    }

    public function testAClinicThatHasSaidNothingIsEmpty(): void
    {
        self::assertTrue(OpeningHours::empty()->isEmpty());
        self::assertFalse(OpeningHours::create([1 => [OpeningSlot::create('09:00', '12:00')]])->isEmpty());
    }
}
