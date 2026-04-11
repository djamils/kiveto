<?php

declare(strict_types=1);

namespace App\Tests\Integration\Fixtures;

use App\Fixtures\Dataset\ClinicDataset;
use App\Shared\Domain\Time\ClockInterface;
use App\Tests\Shared\Time\FrozenClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

final class SchedulingSeedSmokeTest extends KernelTestCase
{
    use Factories;

    public function testSeedProducesAtLeastOneAppointmentPerDayOfCurrentWeek(): void
    {
        $frozenNow = new \DateTimeImmutable('2026-04-15 10:00:00', new \DateTimeZone('Europe/Paris'));
        self::getContainer()->set(ClockInterface::class, new FrozenClock($frozenNow));

        ClinicDataset::load();

        $conn = self::getContainer()->get(Connection::class);
        \assert($conn instanceof Connection);

        // Monday 2026-04-13 00:00 Paris → 2026-04-12 22:00 UTC
        // Sunday 2026-04-19 23:59:59 Paris → 2026-04-19 21:59:59 UTC
        $rows = $conn->fetchAllAssociative(
            'SELECT DATE(starts_at_utc) AS day, COUNT(*) AS cnt
             FROM scheduling__appointments
             WHERE starts_at_utc >= :from AND starts_at_utc <= :to
             GROUP BY DATE(starts_at_utc)
             ORDER BY day ASC',
            [
                'from' => '2026-04-12 22:00:00',
                'to'   => '2026-04-19 21:59:59',
            ],
        );

        self::assertGreaterThanOrEqual(7, \count($rows));
    }

    public function testSeedContainsAtLeastOneAppointmentWithApostropheInReason(): void
    {
        self::getContainer()->set(
            ClockInterface::class,
            new FrozenClock(new \DateTimeImmutable('2026-04-15 10:00:00', new \DateTimeZone('Europe/Paris'))),
        );

        ClinicDataset::load();

        $conn = self::getContainer()->get(Connection::class);
        \assert($conn instanceof Connection);

        $count = $conn->fetchOne(
            "SELECT COUNT(*) FROM scheduling__appointments WHERE reason LIKE '%d\\'Artagnan%'",
        );
        \assert(is_numeric($count));

        self::assertGreaterThan(0, (int) $count);
    }

    public function testSeedContainsAtLeastOneAppointmentWithAccentInReason(): void
    {
        self::getContainer()->set(
            ClockInterface::class,
            new FrozenClock(new \DateTimeImmutable('2026-04-15 10:00:00', new \DateTimeZone('Europe/Paris'))),
        );

        ClinicDataset::load();

        $conn = self::getContainer()->get(Connection::class);
        \assert($conn instanceof Connection);

        $count = $conn->fetchOne(
            "SELECT COUNT(*) FROM scheduling__appointments WHERE reason LIKE '%Contrôle%'",
        );
        \assert(is_numeric($count));

        self::assertGreaterThan(0, (int) $count);
    }
}
