<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Infrastructure\Persistence\Doctrine\TlsConnectionDriver;
use App\Shared\Infrastructure\Persistence\Doctrine\TlsConnectionMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Pdo\Mysql;
use PHPUnit\Framework\TestCase;

final class TlsConnectionMiddlewareTest extends TestCase
{
    private string $caFile;

    protected function setUp(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ca');
        \assert(\is_string($file));
        $this->caFile = $file;
    }

    protected function tearDown(): void
    {
        @unlink($this->caFile);
    }

    public function testWithoutABundleTheDriverIsUnchanged(): void
    {
        $driver = $this->createStub(Driver::class);

        self::assertSame($driver, new TlsConnectionMiddleware('')->wrap($driver));
    }

    public function testWithABundleEveryConnectionUsesTlsAndVerifiesTheServer(): void
    {
        $connection = $this->createStub(Connection::class);
        $driver     = $this->createMock(Driver::class);
        $driver->expects(self::once())->method('connect')->with([
            'host'          => 'db.example',
            'driverOptions' => [
                \PDO::ATTR_TIMEOUT                 => 5,
                Mysql::ATTR_SSL_CA                 => $this->caFile,
                Mysql::ATTR_SSL_VERIFY_SERVER_CERT => true,
            ],
        ])->willReturn($connection);

        $wrapped = new TlsConnectionMiddleware($this->caFile)->wrap($driver);

        self::assertInstanceOf(TlsConnectionDriver::class, $wrapped);
        self::assertSame($connection, $wrapped->connect(['host' => 'db.example', 'driverOptions' => [\PDO::ATTR_TIMEOUT => 5]]));
    }

    public function testTheOptionsAreAddedWhenThereAreNoneYet(): void
    {
        $driver = $this->createMock(Driver::class);
        $driver->expects(self::once())->method('connect')->with([
            'driverOptions' => [Mysql::ATTR_SSL_CA => $this->caFile, Mysql::ATTR_SSL_VERIFY_SERVER_CERT => true],
        ])->willReturn($this->createStub(Connection::class));

        new TlsConnectionMiddleware($this->caFile)->wrap($driver)->connect([]);
    }

    public function testAMissingBundleIsRefused(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('DATABASE_SSL_CA: "/nowhere/ca.pem" is not a readable file.');

        new TlsConnectionMiddleware('/nowhere/ca.pem')->wrap($this->createStub(Driver::class));
    }
}
