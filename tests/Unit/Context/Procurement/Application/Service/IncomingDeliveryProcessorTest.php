<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Service;

use App\Context\Procurement\Application\Dto\DeliveryNoteData;
use App\Context\Procurement\Application\Port\PurchaseOrderReadRepositoryInterface;
use App\Context\Procurement\Application\Port\SupplierReceiptReadRepositoryInterface;
use App\Context\Procurement\Application\Service\IncomingDeliveryProcessor;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class IncomingDeliveryProcessorTest extends TestCase
{
    public function testSkipsAlreadyProcessedDelivery(): void
    {
        $receiptRepo = $this->createStub(SupplierReceiptReadRepositoryInterface::class);
        $receiptRepo->method('existsBySupplierAndDeliveryNote')->willReturn(true);

        $poRepo     = $this->createMock(PurchaseOrderReadRepositoryInterface::class);
        $commandBus = $this->createStub(CommandBusInterface::class);
        $connection = $this->createMock(Connection::class);

        // Confirm no PO lookup or DB insert when delivery already exists.
        $poRepo->expects(self::never())->method('findByExternalReference');
        $connection->expects(self::never())->method('executeStatement');

        $processor = new IncomingDeliveryProcessor($commandBus, $poRepo, $receiptRepo, $connection, new NullLogger());
        $processor->process($this->makeSupplier(), $this->makeAccount(), [
            new DeliveryNoteData('DN-001', 'PO-EXT-001', []),
        ]);
    }

    public function testMatchedDeliveryLogsAndDoesNotInsertUnmatched(): void
    {
        $receiptRepo = $this->createStub(SupplierReceiptReadRepositoryInterface::class);
        $receiptRepo->method('existsBySupplierAndDeliveryNote')->willReturn(false);

        $poRepo = $this->createStub(PurchaseOrderReadRepositoryInterface::class);
        $poRepo->method('findByExternalReference')->willReturn(['id' => 'some-po-id']);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('executeStatement');

        $processor = new IncomingDeliveryProcessor($commandBus, $poRepo, $receiptRepo, $connection, new NullLogger());
        $processor->process($this->makeSupplier(), $this->makeAccount(), [
            new DeliveryNoteData('DN-002', 'PO-EXT-002', []),
        ]);
    }

    public function testUnmatchedDeliveryIsInsertedIntoTable(): void
    {
        $receiptRepo = $this->createStub(SupplierReceiptReadRepositoryInterface::class);
        $receiptRepo->method('existsBySupplierAndDeliveryNote')->willReturn(false);

        $poRepo = $this->createStub(PurchaseOrderReadRepositoryInterface::class);
        $poRepo->method('findByExternalReference')->willReturn(null);

        $commandBus = $this->createStub(CommandBusInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('executeStatement')
            ->with(self::stringContains('procurement__unmatched_deliveries'))
        ;

        $processor = new IncomingDeliveryProcessor($commandBus, $poRepo, $receiptRepo, $connection, new NullLogger());
        $processor->process($this->makeSupplier(), $this->makeAccount(), [
            new DeliveryNoteData('DN-003', 'PO-EXT-003', []),
        ]);
    }

    public function testExceptionDuringProcessingIsCaughtAndLogged(): void
    {
        // Exceptions thrown inside process() are caught and logged; the method must not propagate them.
        $this->expectNotToPerformAssertions();

        $receiptRepo = $this->createStub(SupplierReceiptReadRepositoryInterface::class);
        $receiptRepo->method('existsBySupplierAndDeliveryNote')->willThrowException(new \RuntimeException('DB error'));

        $poRepo     = $this->createStub(PurchaseOrderReadRepositoryInterface::class);
        $commandBus = $this->createStub(CommandBusInterface::class);
        $connection = $this->createStub(Connection::class);

        $processor = new IncomingDeliveryProcessor($commandBus, $poRepo, $receiptRepo, $connection, new NullLogger());
        $processor->process($this->makeSupplier(), $this->makeAccount(), [
            new DeliveryNoteData('DN-004', 'PO-EXT-004', []),
        ]);
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::register(
            id: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
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
            id: SupplierAccountId::fromString('01932b00-0000-7000-8000-000000000002'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            customerCode: CustomerCode::fromString('CVT-12345'),
        );
    }
}
