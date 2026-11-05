<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Counts the SQL statements sent to the database, so that a test can hold a
 * page to its query budget, and keeps how often each one ran, so that a
 * budget overrun names the statement that repeats. Registered as a DBAL
 * middleware in the test environment only (`config/services_test.yaml`).
 *
 * The count is static: DAMA keeps one connection for the whole run, wrapped
 * once, whatever kernel or container a test boots afterwards.
 */
final class QueryCounter implements Middleware
{
    private static int $count = 0;

    /** @var array<string, int> statement => times it ran */
    private static array $statements = [];

    public static function reset(): void
    {
        self::$count      = 0;
        self::$statements = [];
    }

    public static function count(): int
    {
        return self::$count;
    }

    /**
     * The statements run since the last reset, the most repeated first; an
     * `IN` list reads `IN (?…)` whatever its length.
     *
     * @return array<string, int>
     */
    public static function statements(): array
    {
        $statements = self::$statements;
        arsort($statements);

        return $statements;
    }

    public static function record(string $sql): void
    {
        ++self::$count;

        $statement = (string) preg_replace(['/\s+/', '/IN \((\?(, )?)+\)/'], [' ', 'IN (?…)'], trim($sql));

        self::$statements[$statement] = (self::$statements[$statement] ?? 0) + 1;
    }

    public function wrap(Driver $driver): Driver
    {
        return new CountingDriver($driver);
    }
}
