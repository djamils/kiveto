<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * Counts the SQL statements sent to the database, so that a test can hold a
 * page to its query budget. Registered as a DBAL middleware in the test
 * environment only (`config/services_test.yaml`).
 *
 * The count is static: DAMA keeps one connection for the whole run, wrapped
 * once, whatever kernel or container a test boots afterwards.
 */
final class QueryCounter implements Middleware
{
    private static int $count = 0;

    public static function reset(): void
    {
        self::$count = 0;
    }

    public static function count(): int
    {
        return self::$count;
    }

    public static function record(): void
    {
        ++self::$count;
    }

    public function wrap(Driver $driver): Driver
    {
        return new CountingDriver($driver);
    }
}
