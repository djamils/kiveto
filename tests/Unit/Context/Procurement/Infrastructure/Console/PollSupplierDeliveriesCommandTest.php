<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Console;

use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Infrastructure\Console\PollSupplierDeliveriesCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;

final class PollSupplierDeliveriesCommandTest extends TestCase
{
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';

    public function testPollsAllAccountsForSimulationSuppliers(): void
    {
        $supplier = $this->makeSupplier(SupplierIntegrationMode::SIMULATION);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findAll')->willReturn([$supplier]);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => Uuid::fromString(self::ACCOUNT_UUID)->toBinary()],
        ]);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new PollSupplierDeliveriesCommand($commandBus, $supplierRepo, $connection));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Polled 1 supplier account(s)', $tester->getDisplay());
    }

    public function testSkipsSuppliersWithUnsupportedIntegrationMode(): void
    {
        $supplier = $this->makeSupplier(SupplierIntegrationMode::MANUAL_EXPORT);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findAll')->willReturn([$supplier]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchAllAssociative');

        $commandBus = $this->createStub(CommandBusInterface::class);

        $tester = new CommandTester(new PollSupplierDeliveriesCommand($commandBus, $supplierRepo, $connection));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testSkipsSuppliersWithoutActiveAccounts(): void
    {
        $supplier = $this->makeSupplier(SupplierIntegrationMode::SIMULATION);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findAll')->willReturn([$supplier]);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $commandBus = $this->createMock(CommandBusInterface::class);
        $commandBus->expects(self::never())->method('dispatch');

        $tester = new CommandTester(new PollSupplierDeliveriesCommand($commandBus, $supplierRepo, $connection));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testFailureWhenDispatchThrows(): void
    {
        $supplier = $this->makeSupplier(SupplierIntegrationMode::AUTOMATIC);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findAll')->willReturn([$supplier]);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => Uuid::fromString(self::ACCOUNT_UUID)->toBinary()],
        ]);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willThrowException(new \RuntimeException('network failure'));

        $tester = new CommandTester(new PollSupplierDeliveriesCommand($commandBus, $supplierRepo, $connection));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('network failure', $tester->getDisplay());
    }

    public function testUsesDefaultLimitWhenInvalidValue(): void
    {
        $supplier = $this->makeSupplier(SupplierIntegrationMode::SIMULATION);

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findAll')->willReturn([$supplier]);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => Uuid::fromString(self::ACCOUNT_UUID)->toBinary()],
        ]);

        $commandBus = $this->createStub(CommandBusInterface::class);

        $tester = new CommandTester(new PollSupplierDeliveriesCommand($commandBus, $supplierRepo, $connection));
        $tester->execute(['--limit' => 'invalid']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    private function makeSupplier(SupplierIntegrationMode $mode): Supplier
    {
        return Supplier::register(
            id: SupplierId::fromString(self::SUPPLIER_UUID),
            name: SupplierName::fromString('Test'),
            code: SupplierCode::fromString('TEST'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: $mode,
            adapterIdentifier: SupplierIntegrationMode::AUTOMATIC === $mode ? 'centravet_api' : null,
        );
    }
}
