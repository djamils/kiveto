<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository;

use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository\DoctrinePurchaseOrderReadRepository;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository\DoctrineSupplierAccountReadRepository;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository\DoctrineSupplierCatalogReadRepository;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository\DoctrineSupplierPricingReadRepository;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository\DoctrineSupplierReadRepository;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Repository\DoctrineSupplierReceiptReadRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ReadRepositoriesTest extends TestCase
{
    private const string UUID_A = '01932b00-0000-7000-8000-00000000000a';
    private const string UUID_B = '01932b00-0000-7000-8000-00000000000b';
    private const string UUID_C = '01932b00-0000-7000-8000-00000000000c';

    // ============================================================================
    // DoctrineSupplierReadRepository
    // ============================================================================

    public function testSupplierReadRepositoryFindAllReturnsHydratedRows(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            $this->supplierRow(),
        ]);

        $repo = new DoctrineSupplierReadRepository($connection);
        $rows = $repo->findAll();

        self::assertCount(1, $rows);
        self::assertSame(self::UUID_A, $rows[0]['id']);
        self::assertSame('Centravet', $rows[0]['name']);
    }

    public function testSupplierReadRepositoryFindByIdReturnsRow(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->supplierRow());

        $repo = new DoctrineSupplierReadRepository($connection);
        $row  = $repo->findById(self::UUID_A);

        self::assertNotNull($row);
        self::assertSame(self::UUID_A, $row['id']);
    }

    public function testSupplierReadRepositoryFindByIdReturnsNullWhenNotFound(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = new DoctrineSupplierReadRepository($connection);

        self::assertNull($repo->findById(self::UUID_A));
    }

    public function testSupplierReadRepositorySearchReturnsRows(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([$this->supplierRow()]);

        $repo = new DoctrineSupplierReadRepository($connection);
        $rows = $repo->search('Cent');

        self::assertCount(1, $rows);
    }

    // ============================================================================
    // DoctrineSupplierAccountReadRepository
    // ============================================================================

    public function testSupplierAccountReadRepositoryFindByClinic(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([$this->supplierAccountRow()]);

        $repo = new DoctrineSupplierAccountReadRepository($connection);
        $rows = $repo->findByClinic(self::UUID_B);

        self::assertCount(1, $rows);
        self::assertSame('CVT-CUST-001', $rows[0]['customerCode']);
    }

    public function testSupplierAccountReadRepositoryFindById(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->supplierAccountRow());

        $repo = new DoctrineSupplierAccountReadRepository($connection);
        $row  = $repo->findById(self::UUID_A);

        self::assertNotNull($row);
        self::assertSame(self::UUID_A, $row['id']);
    }

    public function testSupplierAccountReadRepositoryFindByIdReturnsNullWhenNotFound(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = new DoctrineSupplierAccountReadRepository($connection);
        self::assertNull($repo->findById(self::UUID_A));
    }

    // ============================================================================
    // DoctrineSupplierCatalogReadRepository
    // ============================================================================

    public function testSupplierCatalogReadRepositorySearch(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([$this->supplierCatalogRow()]);

        $repo = new DoctrineSupplierCatalogReadRepository($connection);
        $rows = $repo->search('Anti', self::UUID_B);

        self::assertCount(1, $rows);
        self::assertSame('Antibiotic', $rows[0]['name']);
    }

    public function testSupplierCatalogReadRepositoryFindById(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->supplierCatalogRow());

        $repo = new DoctrineSupplierCatalogReadRepository($connection);
        $row  = $repo->findById(self::UUID_A);

        self::assertNotNull($row);
        self::assertSame(1299, $row['catalogPriceMinor']);
    }

    public function testSupplierCatalogReadRepositoryFindByIdNull(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = new DoctrineSupplierCatalogReadRepository($connection);
        self::assertNull($repo->findById(self::UUID_A));
    }

    public function testSupplierCatalogReadRepositoryFindBySupplierAndCode(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->supplierCatalogRow());

        $repo = new DoctrineSupplierCatalogReadRepository($connection);
        $row  = $repo->findBySupplierAndCode(self::UUID_B, 'PROD-001');

        self::assertNotNull($row);
    }

    public function testSupplierCatalogReadRepositoryFindBySupplierAndCodeNull(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = new DoctrineSupplierCatalogReadRepository($connection);
        self::assertNull($repo->findBySupplierAndCode(self::UUID_B, 'PROD-001'));
    }

    public function testSupplierCatalogReadRepositoryFindBySupplier(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([$this->supplierCatalogRow()]);

        $repo = new DoctrineSupplierCatalogReadRepository($connection);
        $rows = $repo->findBySupplier(self::UUID_B);

        self::assertCount(1, $rows);
    }

    // ============================================================================
    // DoctrineSupplierPricingReadRepository
    // ============================================================================

    public function testSupplierPricingReadRepositoryFindByClinic(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([$this->supplierPricingRow()]);

        $repo = new DoctrineSupplierPricingReadRepository($connection);
        $rows = $repo->findByClinic(self::UUID_B);

        self::assertCount(1, $rows);
        self::assertSame(999, $rows[0]['amountMinor']);
    }

    public function testSupplierPricingReadRepositoryFindByClinicAndEntry(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->supplierPricingRow());

        $repo = new DoctrineSupplierPricingReadRepository($connection);
        $row  = $repo->findByClinicAndEntry(self::UUID_B, self::UUID_C);

        self::assertNotNull($row);
        self::assertSame('EUR', $row['amountCurrency']);
    }

    public function testSupplierPricingReadRepositoryFindByClinicAndEntryNull(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = new DoctrineSupplierPricingReadRepository($connection);
        self::assertNull($repo->findByClinicAndEntry(self::UUID_B, self::UUID_C));
    }

    // ============================================================================
    // DoctrinePurchaseOrderReadRepository
    // ============================================================================

    public function testPurchaseOrderReadRepositoryFindByClinic(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([$this->purchaseOrderRow()]);

        $repo = new DoctrinePurchaseOrderReadRepository($connection);
        $rows = $repo->findByClinic(self::UUID_B);

        self::assertCount(1, $rows);
        self::assertSame('PO-2026-000001', $rows[0]['orderNumber']);
    }

    public function testPurchaseOrderReadRepositoryFindById(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->purchaseOrderRow());

        $repo = new DoctrinePurchaseOrderReadRepository($connection);
        $row  = $repo->findById(self::UUID_A);

        self::assertNotNull($row);
        self::assertSame(self::UUID_A, $row['id']);
    }

    public function testPurchaseOrderReadRepositoryFindByIdNull(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = new DoctrinePurchaseOrderReadRepository($connection);
        self::assertNull($repo->findById(self::UUID_A));
    }

    public function testPurchaseOrderReadRepositoryFindByExternalReference(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->purchaseOrderRow());

        $repo = new DoctrinePurchaseOrderReadRepository($connection);
        $row  = $repo->findByExternalReference('EXT-42');

        self::assertNotNull($row);
    }

    public function testPurchaseOrderReadRepositoryFindByExternalReferenceNull(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = new DoctrinePurchaseOrderReadRepository($connection);
        self::assertNull($repo->findByExternalReference('EXT-XX'));
    }

    public function testPurchaseOrderReadRepositoryFindStale(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([$this->purchaseOrderRow()]);

        $repo = new DoctrinePurchaseOrderReadRepository($connection);
        $rows = $repo->findStale(7);

        self::assertCount(1, $rows);
    }

    // ============================================================================
    // DoctrineSupplierReceiptReadRepository
    // ============================================================================

    public function testSupplierReceiptReadRepositoryFindByClinic(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([$this->supplierReceiptRow()]);

        $repo = new DoctrineSupplierReceiptReadRepository($connection);
        $rows = $repo->findByClinic(self::UUID_B);

        self::assertCount(1, $rows);
        self::assertSame('DN-2026-001', $rows[0]['deliveryNoteReference']);
    }

    public function testSupplierReceiptReadRepositoryFindById(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn($this->supplierReceiptRow());

        $repo = new DoctrineSupplierReceiptReadRepository($connection);
        $row  = $repo->findById(self::UUID_A);

        self::assertNotNull($row);
        self::assertSame('PENDING_REVIEW', $row['status']);
    }

    public function testSupplierReceiptReadRepositoryFindByIdNull(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = new DoctrineSupplierReceiptReadRepository($connection);
        self::assertNull($repo->findById(self::UUID_A));
    }

    public function testSupplierReceiptReadRepositoryFindPending(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([$this->supplierReceiptRow()]);

        $repo = new DoctrineSupplierReceiptReadRepository($connection);
        $rows = $repo->findPending(self::UUID_B);

        self::assertCount(1, $rows);
    }

    public function testSupplierReceiptReadRepositoryFindUnmatched(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([[
            'id'                      => $this->binaryUuid(self::UUID_A),
            'clinic_id'               => $this->binaryUuid(self::UUID_B),
            'supplier_id'             => $this->binaryUuid(self::UUID_C),
            'delivery_note_reference' => 'DN-UNMATCHED-1',
            'raw_payload_json'        => '[]',
            'received_at'             => '2026-01-15 10:00:00',
        ]]);

        $repo = new DoctrineSupplierReceiptReadRepository($connection);
        $rows = $repo->findUnmatched(self::UUID_B);

        self::assertCount(1, $rows);
        self::assertSame('DN-UNMATCHED-1', $rows[0]['deliveryNoteReference']);
    }

    public function testSupplierReceiptReadRepositoryExistsBySupplierAndDeliveryNoteTrue(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['id' => 'something']);

        $repo = new DoctrineSupplierReceiptReadRepository($connection);

        self::assertTrue($repo->existsBySupplierAndDeliveryNote(self::UUID_C, 'DN-42'));
    }

    public function testSupplierReceiptReadRepositoryExistsBySupplierAndDeliveryNoteFalse(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);

        $repo = new DoctrineSupplierReceiptReadRepository($connection);

        self::assertFalse($repo->existsBySupplierAndDeliveryNote(self::UUID_C, 'DN-42'));
    }

    // ============================================================================
    // Row builders
    // ============================================================================

    private function binaryUuid(string $uuid): string
    {
        return Uuid::fromString($uuid)->toBinary();
    }

    /** @return array<string, mixed> */
    private function supplierRow(): array
    {
        return [
            'id'                   => $this->binaryUuid(self::UUID_A),
            'name'                 => 'Centravet',
            'code'                 => 'CENT',
            'type'                 => 'CENTRALE',
            'country_code'         => 'FR',
            'default_currency'     => 'EUR',
            'integration_mode'     => 'SIMULATION',
            'adapter_identifier'   => 'simulated',
            'status'               => 'ACTIVE',
            'contact_email'        => 'a@b.com',
            'contact_phone'        => null,
            'contact_person'       => null,
            'address_street'       => null,
            'address_line2'        => null,
            'postal_code'          => null,
            'contact_city'         => null,
            'address_country_code' => null,
            'created_at'           => '2026-01-15 10:00:00',
            'updated_at'           => '2026-01-15 10:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function supplierAccountRow(): array
    {
        return [
            'id'                    => $this->binaryUuid(self::UUID_A),
            'clinic_id'             => $this->binaryUuid(self::UUID_B),
            'supplier_id'           => $this->binaryUuid(self::UUID_C),
            'customer_code'         => 'CVT-CUST-001',
            'status'                => 'ACTIVE',
            'billing_address_json'  => null,
            'delivery_address_json' => null,
            'notes'                 => null,
            'created_at'            => '2026-01-15 10:00:00',
            'updated_at'            => '2026-01-15 10:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function supplierCatalogRow(): array
    {
        return [
            'id'                       => $this->binaryUuid(self::UUID_A),
            'supplier_id'              => $this->binaryUuid(self::UUID_B),
            'supplier_product_code'    => 'PROD-001',
            'name'                     => 'Antibiotic',
            'gtin'                     => null,
            'catalog_price_minor'      => 1299,
            'catalog_price_currency'   => 'EUR',
            'catalog_price_valid_from' => '2026-01-01',
            'catalog_price_valid_to'   => null,
            'packaging_unit'           => null,
            'packaging_amount'         => null,
            'status'                   => 'ACTIVE',
            'created_at'               => '2026-01-15 10:00:00',
            'updated_at'               => '2026-01-15 10:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function supplierPricingRow(): array
    {
        return [
            'id'                        => $this->binaryUuid(self::UUID_A),
            'clinic_id'                 => $this->binaryUuid(self::UUID_B),
            'supplier_id'               => $this->binaryUuid(self::UUID_C),
            'supplier_catalog_entry_id' => $this->binaryUuid(self::UUID_A),
            'amount_minor'              => 999,
            'amount_currency'           => 'EUR',
            'discount_percentage'       => '10.00',
            'pricing_notes'             => null,
            'expires_at'                => null,
            'negotiated_at'             => '2026-01-15 10:00:00',
            'created_at'                => '2026-01-15 10:00:00',
            'updated_at'                => '2026-01-15 10:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function purchaseOrderRow(): array
    {
        return [
            'id'                             => $this->binaryUuid(self::UUID_A),
            'clinic_id'                      => $this->binaryUuid(self::UUID_B),
            'supplier_id'                    => $this->binaryUuid(self::UUID_C),
            'supplier_account_id'            => $this->binaryUuid(self::UUID_A),
            'order_number'                   => 'PO-2026-000001',
            'currency'                       => 'EUR',
            'status'                         => 'DRAFT',
            'external_reference_value'       => null,
            'external_reference_provided_by' => null,
            'delivery_address_json'          => null,
            'pdf_file_id'                    => null,
            'submitted_at'                   => null,
            'confirmed_at'                   => null,
            'created_at'                     => '2026-01-15 10:00:00',
            'updated_at'                     => '2026-01-15 10:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function supplierReceiptRow(): array
    {
        return [
            'id'                      => $this->binaryUuid(self::UUID_A),
            'clinic_id'               => $this->binaryUuid(self::UUID_B),
            'supplier_id'             => $this->binaryUuid(self::UUID_C),
            'purchase_order_id'       => $this->binaryUuid(self::UUID_A),
            'delivery_note_reference' => 'DN-2026-001',
            'match_type'              => 'AUTO_MATCHED',
            'status'                  => 'PENDING_REVIEW',
            'validated_at'            => null,
            'received_by'             => null,
            'comment'                 => null,
            'created_at'              => '2026-01-15 10:00:00',
            'updated_at'              => '2026-01-15 10:00:00',
        ];
    }
}
