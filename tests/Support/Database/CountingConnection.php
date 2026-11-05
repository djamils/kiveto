<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * Counts every statement it runs: the prepared ones when executed, the
 * others when sent.
 */
final class CountingConnection extends AbstractConnectionMiddleware
{
    public function prepare(string $sql): Statement
    {
        return new CountingStatement(parent::prepare($sql), $sql);
    }

    public function query(string $sql): Result
    {
        QueryCounter::record($sql);

        return parent::query($sql);
    }

    public function exec(string $sql): int|string
    {
        QueryCounter::record($sql);

        return parent::exec($sql);
    }
}
