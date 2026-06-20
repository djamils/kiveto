<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context\Procurement\Persistence;

use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderId;
use App\Context\Procurement\Domain\PurchaseOrder\ValueObject\PurchaseOrderLineId;
use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Domain\Supplier\ValueObject\SupplierId;
use App\Context\Procurement\Domain\SupplierReceipt\Entity\SupplierReceiptLine;
use App\Context\Procurement\Domain\SupplierReceipt\SupplierReceipt;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\DeliveryNoteReference;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\LotInformation;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\ReceiptMatchType;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptLineId;
use App\Context\Procurement\Domain\SupplierReceipt\ValueObject\SupplierReceiptStatus;
use App\Context\Procurement\Infrastructure\Persistence\Doctrine\Mapper\SupplierReceiptMapper;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Pure PHP round-trip test for SupplierReceiptMapper.
 * Covers lines with LotInformation and actualUnitPrice serialisation.
 */
final class SupplierReceiptMapperTest extends TestCase
{
    private SupplierReceiptMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new SupplierReceiptMapper();
    }

    public function testRoundTripPendingReceiptWithoutLines(): void
    {
        $now     = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $receipt = SupplierReceipt::reconstitute(
            id: SupplierReceiptId::fromString('01932b00-0000-7000-8000-000000000400'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            purchaseOrderId: PurchaseOrderId::fromString('01932b00-0000-7000-8000-000000000300'),
            deliveryNoteReference: DeliveryNoteReference::fromString('DN-2026-001'),
            matchType: ReceiptMatchType::AUTO_MATCHED,
            status: SupplierReceiptStatus::PENDING_REVIEW,
            validatedAt: null,
            receivedBy: null,
            comment: 'Test comment',
            lines: [],
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($receipt);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame($receipt->id()->toString(), $reconstituted->id()->toString());
        self::assertSame($receipt->clinicId()->toString(), $reconstituted->clinicId()->toString());
        self::assertSame($receipt->supplierId()->toString(), $reconstituted->supplierId()->toString());
        self::assertSame($receipt->purchaseOrderId()->toString(), $reconstituted->purchaseOrderId()->toString());
        self::assertSame('DN-2026-001', $reconstituted->deliveryNoteReference()->toString());
        self::assertSame(ReceiptMatchType::AUTO_MATCHED, $reconstituted->matchType());
        self::assertSame(SupplierReceiptStatus::PENDING_REVIEW, $reconstituted->status());
        self::assertNull($reconstituted->validatedAt());
        self::assertNull($reconstituted->receivedBy());
        self::assertSame('Test comment', $reconstituted->comment());
        self::assertCount(0, $reconstituted->lines());
        self::assertSame(1, $reconstituted->version());
    }

    public function testRoundTripValidatedReceiptWithLinesAndLotInformation(): void
    {
        $now       = new \DateTimeImmutable('2026-01-15T10:00:00+00:00');
        $validated = new \DateTimeImmutable('2026-01-16T12:00:00+00:00');
        $expiry    = new \DateTimeImmutable('2027-06-30');
        $mfgAt     = new \DateTimeImmutable('2025-12-01');

        // Line with lot information and actual price
        $lineWithLot = SupplierReceiptLine::reconstitute(
            id: SupplierReceiptLineId::fromString('01932b00-0000-7000-8000-000000000401'),
            purchaseOrderLineId: PurchaseOrderLineId::fromString('01932b00-0000-7000-8000-000000000301'),
            articleId: ArticleId::fromString('01932b00-0000-7000-8000-000000000200'),
            receivedAmount: '5',
            receivedUnit: UnitOfMeasure::fromString('UNIT'),
            lotInformation: LotInformation::create('LOT-ABC-2025', $expiry, $mfgAt),
            actualUnitPrice: Money::fromMinorUnits(1480, CurrencyCode::fromString('EUR')),
            note: 'Checking lot',
        );

        // Line without lot information or actual price
        $lineWithoutLot = SupplierReceiptLine::reconstitute(
            id: SupplierReceiptLineId::fromString('01932b00-0000-7000-8000-000000000402'),
            purchaseOrderLineId: PurchaseOrderLineId::fromString('01932b00-0000-7000-8000-000000000302'),
            articleId: ArticleId::fromString('01932b00-0000-7000-8000-000000000201'),
            receivedAmount: '3',
            receivedUnit: UnitOfMeasure::fromString('BOX'),
            lotInformation: null,
            actualUnitPrice: null,
            note: null,
        );

        $receipt = SupplierReceipt::reconstitute(
            id: SupplierReceiptId::fromString('01932b00-0000-7000-8000-000000000403'),
            clinicId: ClinicId::fromString('01932b00-0000-7000-8000-000000000003'),
            supplierId: SupplierId::fromString('01932b00-0000-7000-8000-000000000001'),
            purchaseOrderId: PurchaseOrderId::fromString('01932b00-0000-7000-8000-000000000303'),
            deliveryNoteReference: DeliveryNoteReference::fromString('DN-2026-042'),
            matchType: ReceiptMatchType::MANUALLY_CREATED,
            status: SupplierReceiptStatus::VALIDATED,
            validatedAt: $validated,
            receivedBy: '01932b00-0000-7000-8000-000000000500',
            comment: null,
            lines: [$lineWithLot, $lineWithoutLot],
            version: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $entity        = $this->mapper->toEntity($receipt);
        $reconstituted = $this->mapper->toDomain($entity);

        self::assertSame(SupplierReceiptStatus::VALIDATED, $reconstituted->status());
        self::assertSame(ReceiptMatchType::MANUALLY_CREATED, $reconstituted->matchType());
        $reconstitutedValidatedAt = $reconstituted->validatedAt();
        self::assertNotNull($reconstitutedValidatedAt);
        self::assertSame($validated->getTimestamp(), $reconstitutedValidatedAt->getTimestamp());
        self::assertSame('01932b00-0000-7000-8000-000000000500', $reconstituted->receivedBy());
        self::assertNull($reconstituted->comment());
        self::assertCount(2, $reconstituted->lines());
        self::assertSame(1, $reconstituted->version());

        // Verify line with lot
        $reconstitutedLine1 = $reconstituted->lines()[0];
        self::assertSame('5', $reconstitutedLine1->receivedAmount());
        self::assertSame('UNIT', $reconstitutedLine1->receivedUnit()->toString());

        $lot1 = $reconstitutedLine1->lotInformation();
        self::assertNotNull($lot1);
        self::assertSame('LOT-ABC-2025', $lot1->lotNumber);
        self::assertSame($expiry->getTimestamp(), $lot1->expiryDate->getTimestamp());

        $mfgDate = $lot1->manufacturedAt;
        self::assertNotNull($mfgDate);
        self::assertSame($mfgAt->getTimestamp(), $mfgDate->getTimestamp());

        $actualPrice1 = $reconstitutedLine1->actualUnitPrice();
        self::assertNotNull($actualPrice1);
        self::assertSame(1480, $actualPrice1->minorUnits());
        self::assertSame('EUR', $actualPrice1->currency()->toString());
        self::assertSame('Checking lot', $reconstitutedLine1->note());

        // Verify line without lot
        $reconstitutedLine2 = $reconstituted->lines()[1];
        self::assertSame('3', $reconstitutedLine2->receivedAmount());
        self::assertSame('BOX', $reconstitutedLine2->receivedUnit()->toString());
        self::assertNull($reconstitutedLine2->lotInformation());
        self::assertNull($reconstitutedLine2->actualUnitPrice());
        self::assertNull($reconstitutedLine2->note());
    }
}
