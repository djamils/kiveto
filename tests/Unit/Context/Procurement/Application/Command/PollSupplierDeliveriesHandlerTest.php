<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\PollSupplierDeliveries\PollSupplierDeliveries;
use App\Context\Procurement\Application\Command\PollSupplierDeliveries\PollSupplierDeliveriesHandler;
use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use App\Context\Procurement\Application\Port\SupplierIntegrationAdapterInterface;
use App\Context\Procurement\Application\Port\SupplierReceiptReadRepositoryInterface;
use App\Context\Procurement\Application\Service\IncomingDeliveryProcessor;
use App\Context\Procurement\Application\Service\SupplierIntegrationDispatcher;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\Exception\SupplierNotFoundException;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Domain\SupplierAccount\Exception\SupplierAccountNotFoundException;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class PollSupplierDeliveriesHandlerTest extends TestCase
{
    private const string SUPPLIER_UUID = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID  = '01932b00-0000-7000-8000-000000000002';
    private const string CLINIC_UUID   = '01932b00-0000-7000-8000-000000000003';

    public function testItFetchesDeliveriesAndProcessesThem(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($this->makeSupplier());

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $accountRepo->method('findById')->willReturn($this->makeAccount());

        $adapter = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('sim');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);
        $adapter->method('supportsAsyncDeliveryPolling')->willReturn(true);
        $adapter->method('fetchDeliveries')->willReturn([]);

        ($this->makeHandler($supplierRepo, $accountRepo, $adapter))(new PollSupplierDeliveries(
            supplierId: self::SUPPLIER_UUID,
            supplierAccountId: self::ACCOUNT_UUID,
        ));

        $this->addToAssertionCount(1);
    }

    public function testItSkipsNonPollingAdapters(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($this->makeSupplier());

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $accountRepo->method('findById')->willReturn($this->makeAccount());

        // Adapter reports it does not support polling
        $adapter = $this->createMock(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('sim');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);
        $adapter->method('supportsAsyncDeliveryPolling')->willReturn(false);
        $adapter->expects(self::never())->method('fetchDeliveries');

        ($this->makeHandler($supplierRepo, $accountRepo, $adapter))(new PollSupplierDeliveries(
            supplierId: self::SUPPLIER_UUID,
            supplierAccountId: self::ACCOUNT_UUID,
        ));
    }

    public function testItThrowsWhenSupplierNotFound(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn(null);

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $adapter     = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('sim');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);

        $this->expectException(SupplierNotFoundException::class);

        ($this->makeHandler($supplierRepo, $accountRepo, $adapter))(new PollSupplierDeliveries(
            supplierId: self::SUPPLIER_UUID,
            supplierAccountId: self::ACCOUNT_UUID,
        ));
    }

    public function testItThrowsWhenSupplierAccountNotFound(): void
    {
        $supplierRepo = $this->createStub(SupplierRepositoryInterface::class);
        $supplierRepo->method('findById')->willReturn($this->makeSupplier());

        $accountRepo = $this->createStub(SupplierAccountRepositoryInterface::class);
        $accountRepo->method('findById')->willReturn(null);

        $adapter = $this->createStub(SupplierIntegrationAdapterInterface::class);
        $adapter->method('identifier')->willReturn('sim');
        $adapter->method('mode')->willReturn(SupplierIntegrationMode::SIMULATION);

        $this->expectException(SupplierAccountNotFoundException::class);

        ($this->makeHandler($supplierRepo, $accountRepo, $adapter))(new PollSupplierDeliveries(
            supplierId: self::SUPPLIER_UUID,
            supplierAccountId: self::ACCOUNT_UUID,
        ));
    }

    private function makeHandler(
        SupplierRepositoryInterface $supplierRepo,
        SupplierAccountRepositoryInterface $accountRepo,
        SupplierIntegrationAdapterInterface $adapter,
    ): PollSupplierDeliveriesHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-15 12:00:00'));

        $commandBus      = $this->createStub(CommandBusInterface::class);
        $connection      = $this->createStub(Connection::class);
        $poReadRepo      = $this->createStub(PurchaseOrderReadRepositoryInterface::class);
        $receiptReadRepo = $this->createStub(SupplierReceiptReadRepositoryInterface::class);

        $processor = new IncomingDeliveryProcessor(
            $commandBus,
            $poReadRepo,
            $receiptReadRepo,
            $connection,
            new NullLogger(),
        );

        return new PollSupplierDeliveriesHandler(
            $supplierRepo,
            $accountRepo,
            new SupplierIntegrationDispatcher([$adapter]),
            $processor,
            $clock,
        );
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

    private function makeAccount(): SupplierAccount
    {
        return SupplierAccount::create(
            id: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            customerCode: CustomerCode::fromString('CVT-12345'),
        );
    }
}
