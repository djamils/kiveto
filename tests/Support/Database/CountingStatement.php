<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;

/**
 * A prepared statement counted each time it runs.
 */
final class CountingStatement extends AbstractStatementMiddleware
{
    public function execute(): Result
    {
        QueryCounter::record();

        return parent::execute();
    }
}
