<?php

declare(strict_types=1);

namespace App\Tests\Support\Database;

use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Hands out connections that count their statements (QueryCounter).
 */
final class CountingDriver extends AbstractDriverMiddleware
{
    public function connect(#[\SensitiveParameter] array $params): DriverConnection
    {
        return new CountingConnection(parent::connect($params));
    }
}
