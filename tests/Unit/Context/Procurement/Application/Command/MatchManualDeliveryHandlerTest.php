<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Application\Command;

use App\Context\Procurement\Application\Command\MatchManualDelivery\MatchManualDelivery;
use App\Context\Procurement\Application\Command\MatchManualDelivery\MatchManualDeliveryHandler;
use App\Context\Procurement\Application\Exception\ClinicSupplierMismatchException;
use App\Context\Procurement\Application\Exception\UnmatchedDeliveryAlreadyResolvedException;
use App\Context\Procurement\Application\Exception\UnmatchedDeliveryNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\InvalidPurchaseOrderStatusTransitionException;
use App\Context\Procurement\Domain\PurchaseOrder\Exception\PurchaseOrderNotFoundException;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class MatchManualDeliveryHandlerTest extends TestCase
{
    private const string UNMATCHED_UUID = '01932b00-0000-7000-8000-000000000500';
    private const string PO_UUID        = '01932b00-0000-7000-8000-000000000100';
    private const string LINE_UUID      = '01932b00-0000-7000-8000-000000000101';
    private const string CLINIC_UUID    = '01932b00-0000-7000-8000-000000000003';
    private const string SUPPLIER_UUID  = '01932b00-0000-7000-8000-000000000001';
    private const string ACCOUNT_UUID   = '01932b00-0000-7000-8000-000000000002';
    private const string ARTICLE_UUID   = '01932b00-0000-7000-8000-000000000200';
    private const string CATALOG_UUID   = '01932b00-0000-7000-8000-000000000300';

    public function testItMatchesDeliveryAndDispatchesCreateReceipt(): void
    {
        $po = $this->makeConfirmedPurchaseOrder();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->makeUnmatchedRow());
        $connection->expects(self::once())->method('executeStatement');

        $commandBus = $this->createMock(CommandBusInterface::class);
        $commandBus->expects(self::once())->method('dispatch');

        ($this->makeHandler($repository, $connection, $commandBus))(new MatchManualDelivery(
            unmatchedDeliveryId: self::UNMATCHED_UUID,
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
            matchedBy: null,
        ));
    }

    public function testItThrowsWhenUnmatchedDeliveryNotFound(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $commandBus = $this->createStub(CommandBusInterface::class);

        $this->expectException(UnmatchedDeliveryNotFoundException::class);

        ($this->makeHandler($repository, $connection, $commandBus))(new MatchManualDelivery(
            unmatchedDeliveryId: self::UNMATCHED_UUID,
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
            matchedBy: null,
        ));
    }

    public function testItThrowsWhenDeliveryAlreadyResolved(): void
    {
        $row                = $this->makeUnmatchedRow();
        $row['resolved_at'] = '2026-01-10 10:00:00';

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $commandBus = $this->createStub(CommandBusInterface::class);

        $this->expectException(UnmatchedDeliveryAlreadyResolvedException::class);

        ($this->makeHandler($repository, $connection, $commandBus))(new MatchManualDelivery(
            unmatchedDeliveryId: self::UNMATCHED_UUID,
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
            matchedBy: null,
        ));
    }

    public function testItThrowsWhenPurchaseOrderNotFound(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->makeUnmatchedRow());

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $commandBus = $this->createStub(CommandBusInterface::class);

        $this->expectException(PurchaseOrderNotFoundException::class);

        ($this->makeHandler($repository, $connection, $commandBus))(new MatchManualDelivery(
            unmatchedDeliveryId: self::UNMATCHED_UUID,
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
            matchedBy: null,
        ));
    }

    public function testItThrowsWhenClinicOrSupplierDoesNotMatch(): void
    {
        $po = $this->makeConfirmedPurchaseOrder();

        // Row has a different supplier
        $row                = $this->makeUnmatchedRow();
        $row['supplier_id'] = Uuid::fromString('01932b00-0000-7000-8000-000000000099')->toBinary();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);

        $commandBus = $this->createStub(CommandBusInterface::class);

        $this->expectException(ClinicSupplierMismatchException::class);

        ($this->makeHandler($repository, $connection, $commandBus))(new MatchManualDelivery(
            unmatchedDeliveryId: self::UNMATCHED_UUID,
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
            matchedBy: null,
        ));
    }

    public function testItThrowsWhenPurchaseOrderStatusIsInvalid(): void
    {
        // Create a DRAFT PO (not CONFIRMED — cannot receive)
        $po = PurchaseOrder::create(
            id: PurchaseOrderId::fromString(self::PO_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            supplierAccountId: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000001'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $_ = $po->pullDomainEvents();

        $repository = $this->createStub(PurchaseOrderRepositoryInterface::class);
        $repository->method('findById')->willReturn($po);

        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->makeUnmatchedRow());

        $commandBus = $this->createStub(CommandBusInterface::class);

        $this->expectException(InvalidPurchaseOrderStatusTransitionException::class);

        ($this->makeHandler($repository, $connection, $commandBus))(new MatchManualDelivery(
            unmatchedDeliveryId: self::UNMATCHED_UUID,
            purchaseOrderId: self::PO_UUID,
            clinicId: self::CLINIC_UUID,
            matchedBy: null,
        ));
    }

    private function makeHandler(
        PurchaseOrderRepositoryInterface $repository,
        Connection $connection,
        CommandBusInterface $commandBus,
    ): MatchManualDeliveryHandler {
        return new MatchManualDeliveryHandler($repository, $commandBus, $connection);
    }

    /**
     * @return array<string, mixed>
     */
    private function makeUnmatchedRow(): array
    {
        return [
            'id'                      => Uuid::fromString(self::UNMATCHED_UUID)->toBinary(),
            'clinic_id'               => Uuid::fromString(self::CLINIC_UUID)->toBinary(),
            'supplier_id'             => Uuid::fromString(self::SUPPLIER_UUID)->toBinary(),
            'delivery_note_reference' => 'DN-001',
            'raw_payload_json'        => '[]',
            'received_at'             => '2026-01-14 08:00:00',
            'resolved_at'             => null,
            'resolved_by'             => null,
        ];
    }

    private function makeConfirmedPurchaseOrder(): PurchaseOrder
    {
        $po = PurchaseOrder::create(
            id: PurchaseOrderId::fromString(self::PO_UUID),
            clinicId: ClinicId::fromString(self::CLINIC_UUID),
            supplierId: SupplierId::fromString(self::SUPPLIER_UUID),
            supplierAccountId: SupplierAccountId::fromString(self::ACCOUNT_UUID),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000001'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $_ = $po->pullDomainEvents();

        $po->addLine(
            lineId: PurchaseOrderLineId::fromString(self::LINE_UUID),
            articleId: ArticleId::fromString(self::ARTICLE_UUID),
            catalogEntryId: SupplierCatalogEntryId::fromString(self::CATALOG_UUID),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            note: null,
            updatedAt: new \DateTimeImmutable(),
        );
        $po->markAsSubmitting(new \DateTimeImmutable());
        $po->submit(ExternalReference::create('EXT-001', 'test'), new \DateTimeImmutable(), new \DateTimeImmutable());
        $po->confirm(new \DateTimeImmutable(), new \DateTimeImmutable());
        $_ = $po->pullDomainEvents();

        return $po;
    }
}
