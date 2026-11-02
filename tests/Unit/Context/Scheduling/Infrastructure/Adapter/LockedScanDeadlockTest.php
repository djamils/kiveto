<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\TimeSlot;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Adapter\DbalAppointmentConflictChecker;
use App\Context\Scheduling\Infrastructure\Adapter\DbalPlanningBlockAppointmentCounter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DeadlockException;
use PHPUnit\Framework\TestCase;

/**
 * S4A-02: a deadlock in the locked overlap or capacity scan (taken after the
 * practitioner-day lock) is a contended slot, never a raw DBAL error; and
 * both scans are bounded below by the longest slot.
 */
final class LockedScanDeadlockTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000c0c01';
    private const string VET    = '01960000-0000-7000-8000-0000000c0d01';

    public function testADeadlockInTheOverlapScanIsAContendedSlot(): void
    {
        $this->expectContended();

        new DbalAppointmentConflictChecker($this->deadlocking())->hasOverlapLocked(
            ClinicId::fromString(self::CLINIC),
            UserId::fromString(self::VET),
            new TimeSlot(new \DateTimeImmutable('2026-04-10 08:00:00'), 30),
        );
    }

    public function testADeadlockInTheCapacityScanIsAContendedSlot(): void
    {
        $this->expectContended();

        new DbalPlanningBlockAppointmentCounter($this->deadlocking())->countOccupyingInWindowLocked(
            ClinicId::fromString(self::CLINIC),
            UserId::fromString(self::VET),
            new \DateTimeImmutable('2026-04-10 08:00:00'),
            new \DateTimeImmutable('2026-04-10 12:00:00'),
        );
    }

    public function testBothScansStartOneLongestSlotBeforeTheirWindow(): void
    {
        $from       = [];
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturnCallback(static function (string $sql, array $params) use (&$from): array {
            self::assertStringContainsString('starts_at_utc >= :scanFrom', $sql);
            $from[] = $params['scanFrom'];

            return ['cnt' => 0];
        });

        new DbalAppointmentConflictChecker($connection)->hasOverlapLocked(ClinicId::fromString(self::CLINIC), UserId::fromString(self::VET), new TimeSlot(new \DateTimeImmutable('2026-04-10 08:00:00'), 30));
        new DbalPlanningBlockAppointmentCounter($connection)->countOccupyingInWindowLocked(ClinicId::fromString(self::CLINIC), UserId::fromString(self::VET), new \DateTimeImmutable('2026-04-10 09:00:00'), new \DateTimeImmutable('2026-04-10 12:00:00'));

        self::assertSame(['2026-04-09 08:00:00', '2026-04-09 09:00:00'], $from);
    }

    private function deadlocking(): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willThrowException(new DeadlockException(new class('Deadlock found when trying to get lock') extends AbstractException {
        }, null));

        return $connection;
    }

    private function expectContended(): void
    {
        $this->expectException(SlotTakenException::class);
        $this->expectExceptionMessage('The practitioner\'s agenda is being changed concurrently.');
    }
}
