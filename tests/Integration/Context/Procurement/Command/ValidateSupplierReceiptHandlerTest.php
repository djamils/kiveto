<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Command;

use App\Context\Procurement\Application\Command\RegisterSupplier\RegisterSupplier;
use App\Context\Procurement\Application\Command\ValidateSupplierReceipt\ValidateSupplierReceipt;
use App\Context\Procurement\Domain\PurchaseOrder\Repository\PurchaseOrderRepositoryInterface;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderStatus;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\Repository\SupplierRepositoryInterface;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\Repository\SupplierAccountRepositoryInterface;
use App\Context\Procurement\Domain\SupplierAccount\SupplierAccount;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\CustomerCode;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\Repository\SupplierCatalogEntryRepositoryInterface;
use App\Context\Procurement\Domain\SupplierCatalog\SupplierCatalogEntry;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\CatalogPrice;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductCode;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierProductName;
use App\Context\Procurement\Domain\SupplierReceipt\Repository\SupplierReceiptRepositoryInterface;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptStatus;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;

/**
 * T14.1 — Integration test for ValidateSupplierReceiptHandler.
 *
 * Tests the validate-receipt flow end-to-end, asserting:
 * - receipt.status → VALIDATED
 * - PO.status → PARTIALLY_RECEIVED (or RECEIVED for full delivery)
 * - SupplierReceiptCompletedIntegrationEvent is queued in async transport
 *
 * Setup strategy:
 * - Supplier registered via CommandBus; account + catalog entry via domain repositories
 * - PO, lines, and receipt inserted directly via DBAL to bypass Doctrine identity map
 *   (Procurement's mapper-based repositories create fresh ORM entities on each save,
 *   which collides with entities already loaded into the EM identity map by findById)
 * - Only ValidateSupplierReceipt is dispatched via CommandBus — it is the handler under test
 */
final class ValidateSupplierReceiptHandlerTest extends KernelTestCase
{
    use Factories;

    private const string MESSENGER_TABLE = 'shared__messenger_messages';

    private CommandBusInterface $commandBus;
    private EntityManagerInterface $em;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();

        $commandBus = self::getContainer()->get(CommandBusInterface::class);
        \assert($commandBus instanceof CommandBusInterface);
        $this->commandBus = $commandBus;

        $em = self::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;

        $connection = self::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
    }

    public function testValidateReceiptTransitionsPurchaseOrderToPartiallyReceived(): void
    {
        $clinicId                                 = Uuid::v7()->toString();
        [$supplierId, $poId, $lineId, $receiptId] = $this->buildFixture(
            clinicId: $clinicId,
            orderedQty: '10',
            receivedQty: '5',
            code: 'TSUP-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8)),
        );

        $rowsBefore = $this->fetchCountFromTable(self::MESSENGER_TABLE);

        // Dispatch ValidateSupplierReceipt — this is the handler under test
        $this->commandBus->dispatch(new ValidateSupplierReceipt(
            receiptId: $receiptId,
            clinicId: $clinicId,
        ));
        $this->em->clear();

        // Assert receipt.status = VALIDATED
        $receiptRepo = self::getContainer()->get(SupplierReceiptRepositoryInterface::class);
        \assert($receiptRepo instanceof SupplierReceiptRepositoryInterface);
        $foundReceipt = $receiptRepo->findById(SupplierReceiptId::fromString($receiptId));
        self::assertNotNull($foundReceipt);
        self::assertSame(SupplierReceiptStatus::VALIDATED, $foundReceipt->status());

        // Assert PO.status = PARTIALLY_RECEIVED (5 of 10 delivered)
        $this->em->clear();
        $poRepo = self::getContainer()->get(PurchaseOrderRepositoryInterface::class);
        \assert($poRepo instanceof PurchaseOrderRepositoryInterface);
        $foundPo = $poRepo->findById(PurchaseOrderId::fromString($poId));
        self::assertNotNull($foundPo);
        self::assertSame(PurchaseOrderStatus::PARTIALLY_RECEIVED, $foundPo->status());

        // Assert integration event was published to async transport
        $rowsAfter = $this->fetchCountFromTable(self::MESSENGER_TABLE);
        self::assertGreaterThan($rowsBefore, $rowsAfter, 'SupplierReceiptCompletedIntegrationEvent must be queued.');
    }

    public function testValidateReceiptTransitionsPurchaseOrderToReceived(): void
    {
        $clinicId                                 = Uuid::v7()->toString();
        [$supplierId, $poId, $lineId, $receiptId] = $this->buildFixture(
            clinicId: $clinicId,
            orderedQty: '5',
            receivedQty: '5', // Full delivery
            code: 'FRSUP-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8)),
        );

        // Validate receipt
        $this->commandBus->dispatch(new ValidateSupplierReceipt(
            receiptId: $receiptId,
            clinicId: $clinicId,
        ));
        $this->em->clear();

        // Assert PO.status = RECEIVED (full delivery)
        $poRepo = self::getContainer()->get(PurchaseOrderRepositoryInterface::class);
        \assert($poRepo instanceof PurchaseOrderRepositoryInterface);
        $foundPo = $poRepo->findById(PurchaseOrderId::fromString($poId));
        self::assertNotNull($foundPo);
        self::assertSame(PurchaseOrderStatus::RECEIVED, $foundPo->status());
    }

    /**
     * Builds the full test fixture using DBAL for PO/receipt to avoid EM identity map collisions.
     *
     * @return array{0: string, 1: string, 2: string, 3: string} [supplierId, poId, lineId, receiptId]
     */
    private function buildFixture(
        string $clinicId,
        string $orderedQty,
        string $receivedQty,
        string $code,
    ): array {
        // Register supplier via CommandBus (no identity map issue — supplier uses its own entity)
        $this->commandBus->dispatch(new RegisterSupplier(
            name: 'Test Supplier ' . $code,
            code: $code,
            type: 'CENTRALE',
            countryCode: 'FR',
            defaultCurrency: 'EUR',
            integrationMode: 'MANUAL_EXPORT',
            adapterIdentifier: null,
        ));
        $this->em->clear();

        $supplierRepo = self::getContainer()->get(SupplierRepositoryInterface::class);
        \assert($supplierRepo instanceof SupplierRepositoryInterface);
        $suppliers = $supplierRepo->findAll();
        $supplier  = end($suppliers);
        \assert(false !== $supplier);
        $supplierId = $supplier->id()->toString();

        // Create supplier account via domain repository (no collision — different entity type)
        $accountRepo = self::getContainer()->get(SupplierAccountRepositoryInterface::class);
        \assert($accountRepo instanceof SupplierAccountRepositoryInterface);
        $accountId = Uuid::v7()->toString();
        $account   = SupplierAccount::create(
            id: SupplierAccountId::fromString($accountId),
            clinicId: ClinicId::fromString($clinicId),
            supplierId: SupplierId::fromString($supplierId),
            customerCode: CustomerCode::fromString('INTTEST-' . strtoupper(substr(Uuid::v7()->toString(), 0, 6))),
        );
        $_ = $account->pullDomainEvents();
        $accountRepo->save($account);
        $this->em->clear();

        // Create catalog entry via domain repository
        $catalogRepo = self::getContainer()->get(SupplierCatalogEntryRepositoryInterface::class);
        \assert($catalogRepo instanceof SupplierCatalogEntryRepositoryInterface);
        $catalogEntryId = Uuid::v7()->toString();
        $articleId      = Uuid::v7()->toString();
        $entry          = SupplierCatalogEntry::add(
            id: SupplierCatalogEntryId::fromString($catalogEntryId),
            supplierId: SupplierId::fromString($supplierId),
            productCode: SupplierProductCode::fromString('PROD-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8))),
            name: SupplierProductName::fromString('Test Product ' . $code),
            gtin: null,
            catalogPrice: CatalogPrice::create(
                Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')),
                new \DateTimeImmutable('2026-01-01'),
            ),
        );
        $_ = $entry->pullDomainEvents();
        $catalogRepo->save($entry);
        $this->em->clear();

        // Insert PO + line + receipt directly via DBAL.
        // This bypasses Doctrine ORM entity management, so these entities are NOT in the
        // identity map. When ValidateSupplierReceipt loads them via findById(), there are no
        // pre-existing managed entities → no EntityIdentityCollisionException.
        $poId      = Uuid::v7()->toString();
        $lineId    = Uuid::v7()->toString();
        $receiptId = Uuid::v7()->toString();
        $rcLineId  = Uuid::v7()->toString();
        $now       = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->connection->executeStatement(
            'INSERT INTO procurement__purchase_orders
             (id, clinic_id, supplier_id, supplier_account_id, order_number, currency, status,
              external_reference_value, external_reference_provided_by,
              delivery_address_json, pdf_file_id, submitted_at, confirmed_at, version, created_at, updated_at)
             VALUES (:id, :clinicId, :supplierId, :accountId, :orderNumber, :currency, :status,
                     :extRef, :extRefBy, :deliveryJson, NULL, :now, :now, 1, :now, :now)',
            [
                'id'           => Uuid::fromString($poId)->toBinary(),
                'clinicId'     => Uuid::fromString($clinicId)->toBinary(),
                'supplierId'   => Uuid::fromString($supplierId)->toBinary(),
                'accountId'    => Uuid::fromString($accountId)->toBinary(),
                'orderNumber'  => 'PO-2026-' . \sprintf('%06d', random_int(1, 99999)),
                'currency'     => 'EUR',
                'status'       => 'CONFIRMED',
                'extRef'       => 'EXT-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8)),
                'extRefBy'     => 'manual',
                'deliveryJson' => json_encode(Address::create(null, null, null, null, null)->toArray()),
                'now'          => $now,
            ],
        );

        $this->connection->executeStatement(
            'INSERT INTO procurement__purchase_order_lines
             (id, purchase_order_id, article_id, catalog_entry_id, ordered_amount, ordered_unit,
              unit_price_minor, unit_price_currency, received_amount, status, note)
             VALUES (:id, :poId, :articleId, :catalogEntryId, :orderedAmount, :unit,
                     :priceMinor, :priceCurrency, :receivedAmount, :status, NULL)',
            [
                'id'             => Uuid::fromString($lineId)->toBinary(),
                'poId'           => Uuid::fromString($poId)->toBinary(),
                'articleId'      => Uuid::fromString($articleId)->toBinary(),
                'catalogEntryId' => Uuid::fromString($catalogEntryId)->toBinary(),
                'orderedAmount'  => $orderedQty,
                'unit'           => 'UNIT',
                'priceMinor'     => 1500,
                'priceCurrency'  => 'EUR',
                'receivedAmount' => '0',
                'status'         => 'ACTIVE',
            ],
        );

        $this->connection->executeStatement(
            'INSERT INTO procurement__supplier_receipts
             (id, clinic_id, supplier_id, purchase_order_id, delivery_note_reference, match_type,
              status, validated_at, received_by, comment, version, created_at, updated_at)
             VALUES (:id, :clinicId, :supplierId, :poId, :dnr, :matchType,
                     :status, NULL, NULL, NULL, 1, :now, :now)',
            [
                'id'         => Uuid::fromString($receiptId)->toBinary(),
                'clinicId'   => Uuid::fromString($clinicId)->toBinary(),
                'supplierId' => Uuid::fromString($supplierId)->toBinary(),
                'poId'       => Uuid::fromString($poId)->toBinary(),
                'dnr'        => 'DN-' . strtoupper(substr(Uuid::v7()->toString(), 0, 8)),
                'matchType'  => 'MANUALLY_CREATED',
                'status'     => 'PENDING_REVIEW',
                'now'        => $now,
            ],
        );

        $this->connection->executeStatement(
            'INSERT INTO procurement__supplier_receipt_lines
             (id, supplier_receipt_id, purchase_order_line_id, article_id, received_amount, received_unit,
              lot_number, lot_expiry_date, lot_manufactured_at,
              actual_unit_price_minor, actual_unit_price_currency, note)
             VALUES (:id, :receiptId, :lineId, :articleId, :receivedAmount, :unit,
                     NULL, NULL, NULL, NULL, NULL, NULL)',
            [
                'id'             => Uuid::fromString($rcLineId)->toBinary(),
                'receiptId'      => Uuid::fromString($receiptId)->toBinary(),
                'lineId'         => Uuid::fromString($lineId)->toBinary(),
                'articleId'      => Uuid::fromString($articleId)->toBinary(),
                'receivedAmount' => $receivedQty,
                'unit'           => 'UNIT',
            ],
        );

        return [$supplierId, $poId, $lineId, $receiptId];
    }

    private function fetchCountFromTable(string $table): int
    {
        $count = $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', $table));
        \assert(\is_string($count) || \is_int($count));

        return (int) $count;
    }
}
