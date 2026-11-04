<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * DATABASE_SSL_CA: the CA bundle that signs the MySQL server's certificate.
 * Set (production, RDS with `require_secure_transport`), every connection —
 * `default` and `rate_limiter` — is made over TLS and the server certificate
 * is verified against it; empty (dev, tests, STAGING and DEMO), the driver is
 * left as it is. Read when a connection is built, so one image serves both.
 */
#[AsMiddleware]
final readonly class TlsConnectionMiddleware implements Middleware
{
    public function __construct(
        #[Autowire(env: 'DATABASE_SSL_CA')]
        private string $caFile,
    ) {
    }

    public function wrap(Driver $driver): Driver
    {
        if ('' === $this->caFile) {
            return $driver;
        }

        if (!is_file($this->caFile) || !is_readable($this->caFile)) {
            throw new \LogicException(\sprintf('DATABASE_SSL_CA: "%s" is not a readable file.', $this->caFile));
        }

        return new TlsConnectionDriver($driver, $this->caFile);
    }
}
