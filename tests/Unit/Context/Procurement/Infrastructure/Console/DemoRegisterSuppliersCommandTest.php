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
use App\Context\Procurement\Infrastructure\Console\DemoRegisterSuppliersCommand;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class DemoRegisterSuppliersCommandTest extends TestCase
{
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';

    public function testFailsWhenClinicOptionMissing(): void
    {
        $tester = new CommandTester(new DemoRegisterSuppliersCommand(
            $this->createStub(CommandBusInterface::class),
            $this->createStub(SupplierRepositoryInterface::class),
            $this->createStub(Connection::class),
        ));
        $tester->execute([]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('--clinic option is required', $tester->getDisplay());
    }

    public function testRegistersNewSuppliers(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findByCode')->willReturnOnConsecutiveCalls(
            null,
            $this->makeSupplier(),
            null,
            $this->makeSupplier(),
            null,
            $this->makeSupplier(),
        );

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false); // No existing account

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoRegisterSuppliersCommand($commandBus, $supplierRepo, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Demo suppliers registered', $tester->getDisplay());
    }

    public function testSkipsExistingSuppliersAndAccounts(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findByCode')->willReturn($this->makeSupplier());

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['id' => 'existing-account-bin']);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturn(new \stdClass()); // Only ImportSupplierCatalog dispatches

        $tester = new CommandTester(new DemoRegisterSuppliersCommand($commandBus, $supplierRepo, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('[SKIP] Supplier', $tester->getDisplay());
        self::assertStringContainsString('Account', $tester->getDisplay());
    }

    public function testHandlesRegisterFailureGracefully(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findByCode')->willReturn(null);

        $connection = $this->createStub(Connection::class);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willThrowException(new \RuntimeException('register failure'));

        $tester = new CommandTester(new DemoRegisterSuppliersCommand($commandBus, $supplierRepo, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID]);

        // Despite errors, command exits SUCCESS to allow other suppliers to be processed
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('register failure', $tester->getDisplay());
    }

    public function testHandlesSupplierNotFoundAfterRegistration(): void
    {
        // findByCode always returns null — even after registration "succeeds"
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findByCode')->willReturn(null);

        $connection = $this->createStub(Connection::class);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturn(new \stdClass());

        $tester = new CommandTester(new DemoRegisterSuppliersCommand($commandBus, $supplierRepo, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('not found after registration', $tester->getDisplay());
    }

    public function testHandlesAccountCreationFailureGracefully(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findByCode')->willReturn($this->makeSupplier());

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false); // No existing account

        $callCount  = 0;
        $commandBus = $this->createStub(CommandBusInterface::class);
        $commandBus->method('dispatch')->willReturnCallback(static function () use (&$callCount): \stdClass {
            ++$callCount;
            // 1st call per supplier = CreateSupplierAccount → throw
            // 2nd call per supplier = ImportSupplierCatalog → also throw to cover the warning branch
            throw new \RuntimeException('account/catalog error');
        });

        $tester = new CommandTester(new DemoRegisterSuppliersCommand($commandBus, $supplierRepo, $connection));
        $tester->execute(['--clinic' => self::CLINIC_UUID]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('account/catalog error', $tester->getDisplay());
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::register(
            id: SupplierId::fromString(self::SUPPLIER_UUID),
            name: SupplierName::fromString('Centravet'),
            code: SupplierCode::fromString('CENTRAVET'),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::SIMULATION,
            adapterIdentifier: null,
        );
    }
}
