<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\Supplier;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierCode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierIntegrationMode;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierName;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierType;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierReceipt\Entity\SupplierReceiptLine;
use App\Context\Procurement\Domain\SupplierReceipt\Repository\SupplierReceiptRepositoryInterface;
use App\Context\Procurement\Domain\SupplierReceipt\SupplierReceipt;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\DeliveryNoteReference;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\ReceiptMatchType;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptLineId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptStatus;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CountryCode;
use App\Shared\Domain\ValueObject\CurrencyCode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

final class DoctrineSupplierReceiptRepositoryTest extends KernelTestCase
{
    use Factories;

    private SupplierId $supplierId;
    private PurchaseOrderId $purchaseOrderId;
    private ClinicId $clinicId;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->clinicId = ClinicId::fromString(Uuid::v7()->toString());

        // Persist a supplier
        $supplierRepo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($supplierRepo instanceof SupplierRepositoryInterface);
        $this->supplierId = SupplierId::fromString(Uuid::v7()->toString());
        $supplier         = Supplier::register(
            id: $this->supplierId,
            name: SupplierName::fromString('Receipt Supplier'),
            code: SupplierCode::fromString('RCSUP-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8))),
            type: SupplierType::CENTRALE,
            countryCode: CountryCode::fromString('FR'),
            defaultCurrency: CurrencyCode::fromString('EUR'),
            integrationMode: SupplierIntegrationMode::MANUAL_EXPORT,
            adapterIdentifier: null,
        );
        $_ = $supplier->pullDomainEvents();
        $supplierRepo->save($supplier);

        // Persist a supplier account
        $accountRepo = self::getContainer()->get(SupplierAccountRepositoryInterface::class);
        \assert($accountRepo instanceof SupplierAccountRepositoryInterface);
        $accountId = SupplierAccountId::fromString(Uuid::v7()->toString());
        $account   = SupplierAccount::create(
            id: $accountId,
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            customerCode: CustomerCode::fromString('RC-CUST'),
        );
        $_ = $account->pullDomainEvents();
        $accountRepo->save($account);

        // Persist a purchase order
        $poRepo = self::getContainer()->get(PurchaseOrderRepositoryInterface::class);
        \assert($poRepo instanceof PurchaseOrderRepositoryInterface);
        $this->purchaseOrderId = PurchaseOrderId::fromString(Uuid::v7()->toString());
        $order                 = PurchaseOrder::create(
            id: $this->purchaseOrderId,
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            supplierAccountId: $accountId,
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000001'),
            currency: CurrencyCode::fromString('EUR'),
            deliveryAddress: Address::create(null, null, null, null, null),
        );
        $_ = $order->pullDomainEvents();
        $poRepo->save($order);
    }

    public function testSaveAndFindById(): void
    {
        $repo = self::getContainer()->get(SupplierReceiptRepositoryInterface::class);
        \assert($repo instanceof SupplierReceiptRepositoryInterface);

        $receipt = SupplierReceipt::create(
            id: SupplierReceiptId::fromString(Uuid::v7()->toString()),
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            purchaseOrderId: $this->purchaseOrderId,
            deliveryNoteReference: DeliveryNoteReference::fromString('DN-2026-001'),
            matchType: ReceiptMatchType::AUTO_MATCHED,
            comment: 'Test receipt',
        );
        $_ = $receipt->pullDomainEvents();

        $repo->save($receipt);

        $found = $repo->findById($receipt->id());
        self::assertNotNull($found);
        self::assertSame($receipt->id()->toString(), $found->id()->toString());
        self::assertSame($this->clinicId->toString(), $found->clinicId()->toString());
        self::assertSame($this->supplierId->toString(), $found->supplierId()->toString());
        self::assertSame('DN-2026-001', $found->deliveryNoteReference()->toString());
        self::assertSame(ReceiptMatchType::AUTO_MATCHED, $found->matchType());
        self::assertSame(SupplierReceiptStatus::PENDING_REVIEW, $found->status());
        self::assertSame('Test receipt', $found->comment());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierReceiptRepositoryInterface::class);
        \assert($repo instanceof SupplierReceiptRepositoryInterface);

        $result = $repo->findById(SupplierReceiptId::fromString('01932b00-0000-7fff-8000-000000000000'));
        self::assertNull($result);
    }

    public function testFindByDeliveryNoteReference(): void
    {
        $repo = self::getContainer()->get(SupplierReceiptRepositoryInterface::class);
        \assert($repo instanceof SupplierReceiptRepositoryInterface);

        $ref     = DeliveryNoteReference::fromString('DN-UNIQUE-' . substr(Uuid::v7()->toString(), 0, 8));
        $receipt = SupplierReceipt::create(
            id: SupplierReceiptId::fromString(Uuid::v7()->toString()),
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            purchaseOrderId: $this->purchaseOrderId,
            deliveryNoteReference: $ref,
            matchType: ReceiptMatchType::MANUALLY_CREATED,
        );
        $_ = $receipt->pullDomainEvents();

        $repo->save($receipt);

        $found = $repo->findByDeliveryNoteReference($this->supplierId, $ref);
        self::assertNotNull($found);
        self::assertSame($receipt->id()->toString(), $found->id()->toString());
    }

    public function testFindByDeliveryNoteReferenceReturnsNullWhenNotFound(): void
    {
        $repo = self::getContainer()->get(SupplierReceiptRepositoryInterface::class);
        \assert($repo instanceof SupplierReceiptRepositoryInterface);

        $result = $repo->findByDeliveryNoteReference(
            $this->supplierId,
            DeliveryNoteReference::fromString('DN-NONEXISTENT'),
        );
        self::assertNull($result);
    }

    public function testSaveAddsAndRemovesLinesOnExistingReceipt(): void
    {
        $repo = self::getContainer()->get(SupplierReceiptRepositoryInterface::class);
        \assert($repo instanceof SupplierReceiptRepositoryInterface);

        $receiptId = SupplierReceiptId::fromString(Uuid::v7()->toString());
        $receipt   = SupplierReceipt::create(
            id: $receiptId,
            clinicId: $this->clinicId,
            supplierId: $this->supplierId,
            purchaseOrderId: $this->purchaseOrderId,
            deliveryNoteReference: DeliveryNoteReference::fromString('DN-MUT-' . substr(Uuid::v7()->toString(), 0, 6)),
            matchType: ReceiptMatchType::AUTO_MATCHED,
        );
        $lineToKeepId = SupplierReceiptLineId::fromString(Uuid::v7()->toString());
        $receipt->addLine(
            SupplierReceiptLine::create(
                id: $lineToKeepId,
                purchaseOrderLineId: PurchaseOrderLineId::fromString(Uuid::v7()->toString()),
                articleId: ArticleId::fromString(Uuid::v7()->toString()),
                receivedAmount: '5',
                receivedUnit: UnitOfMeasure::fromString('UNIT'),
            ),
            new \DateTimeImmutable(),
        );
        $lineToRemoveId = SupplierReceiptLineId::fromString(Uuid::v7()->toString());
        $receipt->addLine(
            SupplierReceiptLine::create(
                id: $lineToRemoveId,
                purchaseOrderLineId: PurchaseOrderLineId::fromString(Uuid::v7()->toString()),
                articleId: ArticleId::fromString(Uuid::v7()->toString()),
                receivedAmount: '3',
                receivedUnit: UnitOfMeasure::fromString('UNIT'),
            ),
            new \DateTimeImmutable(),
        );
        $_ = $receipt->pullDomainEvents();
        $repo->save($receipt);

        // Reload and add/remove lines
        $reloaded = $repo->findById($receiptId);
        self::assertNotNull($reloaded);

        $newLineId = SupplierReceiptLineId::fromString(Uuid::v7()->toString());
        $reloaded->addLine(
            SupplierReceiptLine::create(
                id: $newLineId,
                purchaseOrderLineId: PurchaseOrderLineId::fromString(Uuid::v7()->toString()),
                articleId: ArticleId::fromString(Uuid::v7()->toString()),
                receivedAmount: '1',
                receivedUnit: UnitOfMeasure::fromString('UNIT'),
            ),
            new \DateTimeImmutable(),
        );
        $reloaded->removeLine($lineToRemoveId, new \DateTimeImmutable());
        $_ = $reloaded->pullDomainEvents();

        $repo->save($reloaded);

        $final = $repo->findById($receiptId);
        self::assertNotNull($final);
        self::assertCount(2, $final->lines());
        $finalIds = array_map(static fn ($l) => $l->id()->toString(), $final->lines());
        self::assertContains($lineToKeepId->toString(), $finalIds);
        self::assertContains($newLineId->toString(), $finalIds);
        self::assertNotContains($lineToRemoveId->toString(), $finalIds);
    }
}
