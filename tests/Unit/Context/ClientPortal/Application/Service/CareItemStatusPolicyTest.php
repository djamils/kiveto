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
