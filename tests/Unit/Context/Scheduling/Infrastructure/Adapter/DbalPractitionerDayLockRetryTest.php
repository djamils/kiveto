<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter;

use App\Context\Scheduling\Domain\Exception\SlotTakenException;
use App\Context\Scheduling\Domain\ValueObject\ClinicId;
use App\Context\Scheduling\Domain\ValueObject\PractitionerDay;
use App\Context\Scheduling\Domain\ValueObject\UserId;
use App\Context\Scheduling\Infrastructure\Adapter\DbalPractitionerDayLock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\DeadlockException;
use PHPUnit\Framework\TestCase;

/**
 * S4a-F13: a deadlock while locking is retried twice, each time in a reopened
 * transaction (MySQL rolled the victim back), then surfaces as a taken slot.
 */
final class DbalPractitionerDayLockRetryTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000c0001';

    public function testADeadlockIsRetriedInAReopenedTransaction(): void
    {
        $statements = [];
        $connection = $this->connection($statements, deadlocks: 2);

        new DbalPractitionerDayLock($connection)->lock(ClinicId::fromString(self::CLINIC), [$this->day('2026-03-12'), $this->day('2026-03-11')]);

        self::assertSame(['INSERT', 'START TRANSACTION', 'INSERT', 'START TRANSACTION', 'INSERT', 'INSERT'], $statements);
    }

    public function testAThirdDeadlockIsASlotTaken(): void
    {
        $statements = [];
        $connection = $this->connection($statements, deadlocks: 3);

        try {
            new DbalPractitionerDayLock($connection)->lock(ClinicId::fromString(self::CLINIC), [$this->day('2026-03-11')]);
            self::fail('The third deadlock should give up.');
        } catch (SlotTakenException $e) {
            self::assertSame(SlotTakenException::CONTENDED, $e->reason);
            self::assertInstanceOf(DeadlockException::class, $e->getPrevious());
        }

        self::assertSame(['INSERT', 'START TRANSACTION', 'INSERT', 'START TRANSACTION', 'INSERT'], $statements);
    }

    public function testOutsideATransactionNothingIsReopened(): void
    {
        $statements = [];
        $connection = $this->connection($statements, deadlocks: 1, inTransaction: false);

        new DbalPractitionerDayLock($connection)->lock(ClinicId::fromString(self::CLINIC), [$this->day('2026-03-11')]);

        self::assertSame(['INSERT', 'INSERT'], $statements);
    }

    /**
     * @param list<string> $statements
     */
    private function connection(array &$statements, int $deadlocks, bool $inTransaction = true): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn($inTransaction);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql) use (&$statements, &$deadlocks): int {
            $statements[] = str_starts_with($sql, 'INSERT') ? 'INSERT' : $sql;
            if (str_starts_with($sql, 'INSERT') && $deadlocks > 0) {
                --$deadlocks;

                throw new DeadlockException(new class('Deadlock found when trying to get lock') extends AbstractException {
                }, null);
            }

            return 1;
        });

        return $connection;
    }

    private function day(string $date): PractitionerDay
    {
        return new PractitionerDay(UserId::fromString('01960000-0000-7000-8000-0000000d0001'), $date);
    }
}
