<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Adapter\DbalPlanningBlockAppointmentCounter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * A count query that yields no row at all is read as an empty window.
 */
final class DbalPlanningBlockAppointmentCounterTest extends TestCase
{
    public function testNoRowCountsAsZero(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $count = new DbalPlanningBlockAppointmentCounter($connection)->countActiveInWindow(
            ClinicId::fromString('01960000-0000-7000-8000-0000000c0c01'),
            UserId::fromString('01960000-0000-7000-8000-0000000c0d01'),
            new \DateTimeImmutable('2026-04-10 08:00:00'),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
        );

        self::assertSame(0, $count);
    }
}
