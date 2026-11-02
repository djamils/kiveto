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
 * S4A-05: a deadlock while locking is never retried inside the adapter (MySQL
 * rolled the caller's whole transaction back, earlier writes included): it
 * surfaces at once as a contended slot, nothing is reopened, and the command
 * bus runs the whole command again.
 */
final class DbalPractitionerDayLockRetryTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-0000000c0001';

    public function testTheKeysAreTakenInOrder(): void
    {
        $statements = [];
        $connection = $this->connection($statements, deadlocks: 0);

        new DbalPractitionerDayLock($connection)->lock(ClinicId::fromString(self::CLINIC), [$this->day('2026-03-12'), $this->day('2026-03-11')]);

        self::assertSame(['INSERT 2026-03-11', 'INSERT 2026-03-12'], $statements);
    }

    public function testADeadlockIsAContendedSlotAndNoTransactionIsReopened(): void
    {
        $statements = [];
        $connection = $this->connection($statements, deadlocks: 1);

        try {
            new DbalPractitionerDayLock($connection)->lock(ClinicId::fromString(self::CLINIC), [$this->day('2026-03-11'), $this->day('2026-03-12')]);
            self::fail('The deadlock should reach the caller.');
        } catch (SlotTakenException $e) {
            self::assertSame(SlotTakenException::CONTENDED, $e->reason);
            self::assertInstanceOf(DeadlockException::class, $e->getPrevious());
        }

        // No retry, no raw START TRANSACTION that would desync the caller's nesting level
        self::assertSame(['INSERT 2026-03-11'], $statements);
    }

    /**
     * @param list<string> $statements
     */
    private function connection(array &$statements, int $deadlocks): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(static function (string $sql, array $params) use (&$statements, &$deadlocks): int {
            \assert(\is_string($params['day'] ?? null));
            $statements[] = str_starts_with($sql, 'INSERT') ? 'INSERT ' . $params['day'] : $sql;
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
