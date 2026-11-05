<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * A prepared statement counted each time it runs.
 */
final class CountingStatement extends AbstractStatementMiddleware
{
    public function __construct(Statement $wrappedStatement, private readonly string $sql)
    {
        parent::__construct($wrappedStatement);
    }

    public function execute(): Result
    {
        QueryCounter::record($this->sql);

        return parent::execute();
    }
}
