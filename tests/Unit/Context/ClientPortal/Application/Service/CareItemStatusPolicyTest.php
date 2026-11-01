<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Service\CareItemStatus;
use App\Context\ClientPortal\Application\Service\CareItemStatusPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CareItemStatusPolicyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, CareItemStatus}>
     */
    public static function provideTheStatusFollowsTheDueDateCases(): iterable
    {
        yield 'yesterday' => ['2026-10-28', CareItemStatus::OVERDUE];
        yield 'today' => ['2026-10-29', CareItemStatus::DUE_SOON];
        yield 'in 30 days' => ['2026-11-28', CareItemStatus::DUE_SOON];
        yield 'in 31 days' => ['2026-11-29', CareItemStatus::UP_TO_DATE];
    }

    #[DataProvider('provideTheStatusFollowsTheDueDateCases')]
    public function testTheStatusFollowsTheDueDate(string $dueOn, CareItemStatus $expected): void
    {
        $today = new \DateTimeImmutable('2026-10-29', new \DateTimeZone('Pacific/Noumea'));

        self::assertSame($expected, new CareItemStatusPolicy()->statusOf(new \DateTimeImmutable($dueOn, new \DateTimeZone('UTC')), $today));
    }

    /**
     * @return iterable<string, array{string, list<string>, CareItemStatus}>
     */
    public static function provideABookingInTheWindowMakesTheItemBookedCases(): iterable
    {
        // Today is 2026-10-29.
        yield 'due in 10 days, booked in 5' => ['2026-11-08', ['2026-11-03'], CareItemStatus::BOOKED];
        yield 'overdue, booked next week' => ['2026-10-01', ['2026-11-05'], CareItemStatus::BOOKED];
        yield 'overdue, booked within 30 days of today' => ['2026-10-01', ['2026-11-28'], CareItemStatus::BOOKED];
        yield 'overdue, booked too late' => ['2026-10-01', ['2026-11-29'], CareItemStatus::OVERDUE];
        yield 'booked today' => ['2026-12-31', ['2026-10-29'], CareItemStatus::BOOKED];
        yield 'booked yesterday' => ['2026-11-08', ['2026-10-28'], CareItemStatus::DUE_SOON];
        yield 'up to date, booked 30 days after the due date' => ['2027-01-10', ['2027-02-09'], CareItemStatus::BOOKED];
        yield 'up to date, booked 31 days after the due date' => ['2027-01-10', ['2027-02-10'], CareItemStatus::UP_TO_DATE];
        yield 'one booking of several in the window' => ['2026-11-08', ['2026-10-01', '2026-11-20'], CareItemStatus::BOOKED];
        yield 'no booking' => ['2026-11-08', [], CareItemStatus::DUE_SOON];
    }

    /**
     * @param list<string> $bookedOn
     */
    #[DataProvider('provideABookingInTheWindowMakesTheItemBookedCases')]
    public function testABookingInTheWindowMakesTheItemBooked(string $dueOn, array $bookedOn, CareItemStatus $expected): void
    {
        $today = new \DateTimeImmutable('2026-10-29', new \DateTimeZone('Pacific/Noumea'));
        $days  = array_map(static fn (string $day): \DateTimeImmutable => new \DateTimeImmutable($day, new \DateTimeZone('Pacific/Noumea')), $bookedOn);

        self::assertSame($expected, new CareItemStatusPolicy()->statusOf(new \DateTimeImmutable($dueOn), $today, $days));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideTheDayOfAnInstantIsTheClinicsCases(): iterable
    {
        yield 'Noumea is already tomorrow' => ['2026-10-29 14:00:00', 'Pacific/Noumea', '2026-10-30'];
        yield 'Martinique is still yesterday' => ['2026-10-30 02:00:00', 'America/Martinique', '2026-10-29'];
        yield 'Paris' => ['2026-10-29 22:59:59', 'Europe/Paris', '2026-10-29'];
    }

    #[DataProvider('provideTheDayOfAnInstantIsTheClinicsCases')]
    public function testTheDayOfAnInstantIsTheClinics(string $utc, string $timeZone, string $day): void
    {
        $at = CareItemStatusPolicy::dayIn(new \DateTimeImmutable($utc, new \DateTimeZone('UTC')), new \DateTimeZone($timeZone));

        self::assertSame($day . ' 00:00:00 ' . $timeZone, $at->format('Y-m-d H:i:s e'));
    }
}
