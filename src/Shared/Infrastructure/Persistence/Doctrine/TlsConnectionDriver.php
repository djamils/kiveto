<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Pdo\Mysql;

/** PDO MySQL over TLS, the server certificate verified (TlsConnectionMiddleware). */
final class TlsConnectionDriver extends AbstractDriverMiddleware
{
    public function __construct(Driver $driver, private readonly string $caFile)
    {
        parent::__construct($driver);
    }

    public function connect(#[\SensitiveParameter] array $params): Connection
    {
        $options = $params['driverOptions'] ?? [];

        $options[Mysql::ATTR_SSL_CA]                 = $this->caFile;
        $options[Mysql::ATTR_SSL_VERIFY_SERVER_CERT] = true;
        $params['driverOptions']                     = $options;

        return parent::connect($params);
    }
}
