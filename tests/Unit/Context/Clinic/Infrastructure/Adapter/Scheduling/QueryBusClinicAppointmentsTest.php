<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Infrastructure\Adapter\Scheduling;

use App\Context\Clinic\Infrastructure\Adapter\Scheduling\QueryBusClinicAppointments;
use App\Context\Scheduling\Application\Query\CountClinicAppointments\CountClinicAppointments;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueryBusClinicAppointmentsTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000cca1';

    #[DataProvider('provideAClinicHasAppointmentsOnceOneIsCountedCases')]
    public function testAClinicHasAppointmentsOnceOneIsCounted(mixed $count, bool $hasAny): void
    {
        $bus = new AnsweringQueryBus()->answer(CountClinicAppointments::class, $count);

        self::assertSame($hasAny, new QueryBusClinicAppointments($bus)->hasAny(self::CLINIC));

        $query = $bus->asked[0] ?? null;
        self::assertInstanceOf(CountClinicAppointments::class, $query);
        self::assertSame([self::CLINIC, '2000-01-01 00:00:00', '2100-01-01 00:00:00', 'UTC'], [
            $query->clinicId, $query->fromUtc->format('Y-m-d H:i:s'), $query->toUtc->format('Y-m-d H:i:s'), $query->toUtc->getTimezone()->getName(),
        ]);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function provideAClinicHasAppointmentsOnceOneIsCountedCases(): iterable
    {
        yield 'some' => [3, true];

        yield 'one' => [1, true];

        yield 'none' => [0, false];

        yield 'no answer' => [null, false];

        yield 'not a count' => ['3', false];
    }
}
