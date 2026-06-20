<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Adapter\Clinic;

use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Infrastructure\Adapter\Clinic\ClinicProviderAdapter;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class ClinicProviderAdapterTest extends TestCase
{
    private const string CLINIC_UUID = '01932b00-0000-7000-8000-000000000003';

    public function testReturnsCurrencyFromClinicRow(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['currency_code' => 'USD']);

        $adapter = new ClinicProviderAdapter($connection);

        self::assertSame('USD', $adapter->getCurrency(ClinicId::fromString(self::CLINIC_UUID)));
    }

    public function testReturnsEurWhenRowNotFound(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $adapter = new ClinicProviderAdapter($connection);

        self::assertSame('EUR', $adapter->getCurrency(ClinicId::fromString(self::CLINIC_UUID)));
    }

    public function testReturnsEurWhenCurrencyCodeIsMissing(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['other_col' => 'foo']);

        $adapter = new ClinicProviderAdapter($connection);

        self::assertSame('EUR', $adapter->getCurrency(ClinicId::fromString(self::CLINIC_UUID)));
    }

    public function testReturnsEurWhenCurrencyCodeIsNotString(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['currency_code' => 42]);

        $adapter = new ClinicProviderAdapter($connection);

        self::assertSame('EUR', $adapter->getCurrency(ClinicId::fromString(self::CLINIC_UUID)));
    }

    public function testReturnsEurWhenCurrencyCodeIsEmptyString(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['currency_code' => '']);

        $adapter = new ClinicProviderAdapter($connection);

        self::assertSame('EUR', $adapter->getCurrency(ClinicId::fromString(self::CLINIC_UUID)));
    }
}
