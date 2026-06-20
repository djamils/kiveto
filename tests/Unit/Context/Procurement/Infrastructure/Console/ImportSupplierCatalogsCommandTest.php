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
use App\Context\Procurement\Infrastructure\Console\ImportSupplierCatalogsCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportSupplierCatalogsCommandTest extends TestCase
{
    public function testImportsAllSuppliers(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findAll')->willReturn([$this->makeSupplier('CENT')]);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new ImportSupplierCatalogsCommand($commandBus, $supplierRepo));
        $tester->execute([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Imported catalogs for 1 supplier(s)', $tester->getDisplay());
    }

    public function testImportsOnlyFilteredSupplier(): void
    {
        $supplier = $this->makeSupplier('CENT');

        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findByCode')->willReturn($supplier);

        $commandBus = $this->createStub(CommandBusInterface::class);

        $tester = new CommandTester(new ImportSupplierCatalogsCommand($commandBus, $supplierRepo));
        $tester->execute(['--supplier-code' => 'cent']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testReportsNoSuppliersFoundForUnknownCode(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findByCode')->willReturn(null);

        $commandBus = $this->createStub(CommandBusInterface::class);

        $tester = new CommandTester(new ImportSupplierCatalogsCommand($commandBus, $supplierRepo));
        $tester->execute(['--supplier-code' => 'UNKNOWN']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No suppliers found', $tester->getDisplay());
    }

    public function testReportsFailureWhenDispatchThrows(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findAll')->willReturn([$this->makeSupplier('CENT')]);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willThrowException(new \RuntimeException('boom'));

        $tester = new CommandTester(new ImportSupplierCatalogsCommand($commandBus, $supplierRepo));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('boom', $tester->getDisplay());
    }

    private function makeSupplier(string $code): Supplier
    {
        return Supplier::register(
            id: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            name: SupplierName::fromString('Test'),
            code: SupplierCode::fromString($code),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::SIMULATION,
            adapterIdentifier: null,
        );
    }
}
