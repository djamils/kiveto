<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\PurchaseOrder\Entity\PurchaseOrderLine;
use App\Context\Procurement\Domain\PurchaseOrder\PurchaseOrder;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\ExternalReference;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineStatus;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderNumber;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderStatus;
use App\Context\Procurement\Domain\Shared\ValueObject\Address;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierAccount\ValueObject\SupplierAccountId;
use App\Context\Procurement\Domain\SupplierCatalog\ValueObject\SupplierCatalogEntryId;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Entity\PurchaseOrderLineEntity;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\PurchaseOrderMapper;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Pure PHP round-trip test for PurchaseOrderMapper.
 * Covers lines, ExternalReference, and deliveryAddress JSON serialisation.
 */
final class PurchaseOrderMapperTest extends TestCase
{
    private PurchaseOrderMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PurchaseOrderMapper();
    }

    public function testRoundTripDraftOrderWithoutLines(): void
    {
        $now   = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $order = PurchaseOrder::reconstitute(
            id: PurchaseOrderId::fromString('01932b00-0000-7000-8000-000000000300'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            supplierAccountId: SupplierAccountId::fromString('01932b00-0000-7000-8000-000000000010'),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000001'),
            currency: CurrencyCode::fromString('EUR'),
            status: PurchaseOrderStatus::DRAFT,
            externalReference: null,
            deliveryAddress: Address::create('5 rue du Vet', null, '69000', 'Lyon', null),
            pdfFileId: null,
            submittedAt: null,
            confirmedAt: null,
            lines: [],
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($order);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($order->id()->toString(), $reconstituted->id()->toString());
        self::assertSame($order->clinicId()->toString(), $reconstituted->clinicId()->toString());
        self::assertSame($order->supplierId()->toString(), $reconstituted->supplierId()->toString());
        self::assertSame($order->supplierAccountId()->toString(), $reconstituted->supplierAccountId()->toString());
        self::assertSame('PO-2026-000001', $reconstituted->orderNumber()->toString());
        self::assertSame('EUR', $reconstituted->currency()->toString());
        self::assertSame(PurchaseOrderStatus::DRAFT, $reconstituted->status());
        self::assertNull($reconstituted->externalReference());
        self::assertSame('5 rue du Vet', $reconstituted->deliveryAddress()->street);
        self::assertSame('69000', $reconstituted->deliveryAddress()->postalCode);
        self::assertSame('Lyon', $reconstituted->deliveryAddress()->city);
        self::assertNull($reconstituted->deliveryAddress()->countryCode);
        self::assertNull($reconstituted->pdfFileId());
        self::assertNull($reconstituted->submittedAt());
        self::assertNull($reconstituted->confirmedAt());
        self::assertCount(0, $reconstituted->lines());
        self::assertSame(1, $reconstituted->version());
    }

    public function testRoundTripConfirmedOrderWithLinesAndExternalReference(): void
    {
        $now       = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $submitted = new \DateTimeImmutable('2026-01-16T08:00:00+00:00');
        $confirmed = new \DateTimeImmutable('2026-01-17T09:00:00+00:00');

        $line1 = PurchaseOrderLine::reconstitute(
            id: PurchaseOrderLineId::fromString('01932b00-0000-7000-8000-000000000301'),
            articleId: ArticleId::fromString('01932b00-0000-7000-8000-000000000200'),
            catalogEntryId: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000100'),
            orderedAmount: '10',
            orderedUnit: UnitOfMeasure::fromString('UNIT'),
            unitPrice: Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')),
            receivedAmount: '4',
            status: PurchaseOrderLineStatus::ACTIVE,
            note: 'Urgent',
        );

        $line2 = PurchaseOrderLine::reconstitute(
            id: PurchaseOrderLineId::fromString('01932b00-0000-7000-8000-000000000302'),
            articleId: ArticleId::fromString('01932b00-0000-7000-8000-000000000201'),
            catalogEntryId: SupplierCatalogEntryId::fromString('01932b00-0000-7000-8000-000000000101'),
            orderedAmount: '5',
            orderedUnit: UnitOfMeasure::fromString('BOX'),
            unitPrice: Money::fromMinorUnits(3000, CurrencyCode::fromString('EUR')),
            receivedAmount: '0',
            status: PurchaseOrderLineStatus::CANCELLED,
            note: null,
        );

        $order = PurchaseOrder::reconstitute(
            id: PurchaseOrderId::fromString('01932b00-0000-7000-8000-000000000303'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            supplierAccountId: SupplierAccountId::fromString('01932b00-0000-7000-8000-000000000010'),
            orderNumber: PurchaseOrderNumber::fromString('PO-2026-000002'),
            currency: CurrencyCode::fromString('EUR'),
            status: PurchaseOrderStatus::CONFIRMED,
            externalReference: ExternalReference::create('EXT-REF-001', 'centravet-api'),
            deliveryAddress: Address::create('12 rue des Vétérinaires', 'Bâtiment C', '75015', 'Paris', null),
            pdfFileId: 'pdf-file-abc123',
            submittedAt: $submitted,
            confirmedAt: $confirmed,
            lines: [$line1, $line2],
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($order);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(PurchaseOrderStatus::CONFIRMED, $reconstituted->status());
        $extRef = $reconstituted->externalReference();
        self::assertNotNull($extRef);
        self::assertSame('EXT-REF-001', $extRef->value);
        self::assertSame('centravet-api', $extRef->providedBy);
        self::assertSame('12 rue des Vétérinaires', $reconstituted->deliveryAddress()->street);
        self::assertSame('Bâtiment C', $reconstituted->deliveryAddress()->addressLine2);
        self::assertSame('pdf-file-abc123', $reconstituted->pdfFileId());
        self::assertSame($submitted->getTimestamp(), $reconstituted->submittedAt()?->getTimestamp());
        self::assertSame($confirmed->getTimestamp(), $reconstituted->confirmedAt()?->getTimestamp());
        self::assertCount(2, $reconstituted->lines());
        self::assertSame(1, $reconstituted->version());

        $reconstitutedLine1 = $reconstituted->lines()[0];
        self::assertSame('01932b00-0000-7000-8000-000000000301', $reconstitutedLine1->id()->toString());
        self::assertSame('10', $reconstitutedLine1->orderedAmount());
        self::assertSame('UNIT', $reconstitutedLine1->orderedUnit()->toString());
        self::assertSame(1500, $reconstitutedLine1->unitPrice()->minorUnits());
        self::assertSame('4', $reconstitutedLine1->receivedAmount());
        self::assertSame(PurchaseOrderLineStatus::ACTIVE, $reconstitutedLine1->status());
        self::assertSame('Urgent', $reconstitutedLine1->note());

        $reconstitutedLine2 = $reconstituted->lines()[1];
        self::assertSame(PurchaseOrderLineStatus::CANCELLED, $reconstitutedLine2->status());
        self::assertNull($reconstitutedLine2->note());
    }

    public function testToDomainThrowsWhenDeliveryAddressJsonIsNull(): void
    {
        $entity = $this->makeMinimalEntity();
        $entity->setDeliveryAddressJson(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PurchaseOrder delivery_address_json must not be null.');
        $this->mapper->toDomain($entity);
    }

    public function testToDomainThrowsWhenLineHasNonNumericOrderedAmount(): void
    {
        $entity = $this->makeMinimalEntity();
        $line   = $this->makeMinimalLineEntity();
        $line->setOrderedAmount('not-a-number');
        $entity->addLine($line);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid orderedAmount/');
        $this->mapper->toDomain($entity);
    }

    public function testToDomainThrowsWhenLineHasNonNumericReceivedAmount(): void
    {
        $entity = $this->makeMinimalEntity();
        $line   = $this->makeMinimalLineEntity();
        $line->setReceivedAmount('not-a-number');
        $entity->addLine($line);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid receivedAmount/');
        $this->mapper->toDomain($entity);
    }

    private function makeMinimalEntity(): PurchaseOrderEntity
    {
        $entity = new PurchaseOrderEntity();
        $entity->setId(Uuid::fromString('01932b00-0000-7000-8000-000000000400'));
        $entity->setClinicId(Uuid::fromString('01932b00-0000-7000-8000-000000000003'));
        $entity->setSupplierId(Uuid::fromString('01932b00-0000-7000-8000-000000000001'));
        $entity->setSupplierAccountId(Uuid::fromString('01932b00-0000-7000-8000-000000000010'));
        $entity->setOrderNumber('PO-2026-000099');
        $entity->setCurrency('EUR');
        $entity->setStatus(PurchaseOrderStatus::DRAFT->value);
        $entity->setExternalReferenceValue(null);
        $entity->setExternalReferenceProvidedBy(null);
        $entity->setDeliveryAddressJson(json_encode([
            'street'       => null,
            'addressLine2' => null,
            'postalCode'   => null,
            'city'         => null,
            'countryCode'  => null,
        ], \JSON_THROW_ON_ERROR));
        $entity->setPdfFileId(null);
        $entity->setSubmittedAt(null);
        $entity->setConfirmedAt(null);
        $entity->setCreatedAt(new \DateTimeImmutable());
        $entity->setUpdatedAt(new \DateTimeImmutable());

        return $entity;
    }

    private function makeMinimalLineEntity(): PurchaseOrderLineEntity
    {
        $line = new PurchaseOrderLineEntity();
        $line->setId(Uuid::fromString('01932b00-0000-7000-8000-000000000401'));
        $line->setArticleId(Uuid::fromString('01932b00-0000-7000-8000-000000000200'));
        $line->setCatalogEntryId(Uuid::fromString('01932b00-0000-7000-8000-000000000100'));
        $line->setOrderedAmount('5');
        $line->setOrderedUnit('UNIT');
        $line->setUnitPriceMinor(1000);
        $line->setUnitPriceCurrency('EUR');
        $line->setReceivedAmount('0');
        $line->setStatus(PurchaseOrderLineStatus::ACTIVE->value);
        $line->setNote(null);

        return $line;
    }
}
