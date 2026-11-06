<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Service;

use App\Context\Clinic\Application\Service\ClinicAccountDates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClinicAccountDatesTest extends TestCase
{
    public function testATypedDayIsMidnightUtc(): void
    {
        $day = ClinicAccountDates::day(' 2027-01-31 ');

        self::assertSame(['2027-01-31 00:00:00', 'UTC'], [$day?->format('Y-m-d H:i:s'), $day?->getTimezone()->getName()]);
    }

    #[DataProvider('provideAnEmptyFieldIsNoDayCases')]
    public function testAnEmptyFieldIsNoDay(?string $value): void
    {
        self::assertNull(ClinicAccountDates::day($value));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideAnEmptyFieldIsNoDayCases(): iterable
    {
        yield 'null' => [null];

        yield 'empty' => [''];

        yield 'blank' => ['   '];
    }

    #[DataProvider('provideWhatIsNotACalendarDayIsRefusedCases')]
    public function testWhatIsNotACalendarDayIsRefused(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('« %s » n’est pas une date (AAAA-MM-JJ).', $value));

        ClinicAccountDates::day($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideWhatIsNotACalendarDayIsRefusedCases(): iterable
    {
        yield 'French order' => ['31/01/2027'];

        yield 'a day that does not exist' => ['2026-02-30'];

        yield 'unpadded' => ['2027-1-5'];

        yield 'with a time' => ['2027-01-31 10:00'];
    }
}
