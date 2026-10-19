<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Billing\Infrastructure\Persistence\Doctrine;

use App\Context\Billing\Domain\CreditNote;
use App\Context\Billing\Domain\ValueObject\ClinicId;
use App\Context\Billing\Domain\ValueObject\CreditNoteId;
use App\Context\Billing\Domain\ValueObject\InvoiceId;
use App\Context\Billing\Domain\ValueObject\InvoiceLine;
use App\Context\Billing\Domain\ValueObject\InvoiceLineId;
use App\Context\Billing\Domain\ValueObject\InvoiceLineSource;
use App\Context\Billing\Domain\ValueObject\InvoiceNumber;
use App\Context\Billing\Domain\ValueObject\LineAmounts;
use App\Context\Billing\Domain\ValueObject\OperationCategory;
use App\Context\Billing\Domain\ValueObject\Payer;
use App\Context\Billing\Domain\ValueObject\PriceMode;
use App\Context\Billing\Domain\ValueObject\RateBucket;
use App\Context\Billing\Infrastructure\Persistence\Doctrine\CreditNoteMapper;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\Shared\Money\Domain\ValueObject\UnitPrice;
use PHPUnit\Framework\TestCase;

final class CreditNoteMapperTest extends TestCase
{
    public function testACreditNoteSurvivesTheRoundTripWithItsFrozenAmounts(): void
    {
        $mapper = new CreditNoteMapper();
        $entity = $mapper->toEntity($this->note());

        self::assertSame([['rate' => '5.5', 'lineTotal' => 1000, 'allowance' => 100, 'vat' => 50]], $entity->getTaxBreakdown());
        self::assertSame(1000, $entity->getTotalNetBeforeAllowance());
        self::assertSame(100, $entity->getTotalAllowance());
        self::assertSame(900, $entity->getTotalNetMinorUnits());
        self::assertSame(950, $entity->getTotalGrossMinorUnits());
        self::assertSame('incl', $entity->getLines()[0]['unitPriceMode']);
        self::assertSame('goods', $entity->getLines()[0]['operationCategory']);

        $restored = $mapper->toDomain($entity);
        $line     = $restored->lines()[0];

        self::assertSame(950, $restored->totalGross()->minorUnits());
        self::assertSame(100, $restored->discountAmount()->minorUnits());
        self::assertSame(1055, $line->enteredUnitPrice()->minorUnits());
        self::assertSame(PriceMode::INCLUDING_TAX, $line->priceMode());
        self::assertSame(OperationCategory::GOODS, $line->operationCategory());
        self::assertSame('10.000000', $line->unitNet()->toDecimalString());
        self::assertSame(1055, $line->grossAmount()->minorUnits());
    }

    private function note(): CreditNote
    {
        $eur = CurrencyCode::fromString('EUR');

        return CreditNote::issue(
            id: CreditNoteId::fromString('01960000-0000-7000-8000-00000000c801'),
            clinicId: ClinicId::fromString('01960000-0000-7000-8000-00000000c802'),
            number: InvoiceNumber::of(2026, 4, 'AV'),
            invoiceId: InvoiceId::fromString('01960000-0000-7000-8000-00000000c803'),
            invoiceNumber: 'FAC-2026-000012',
            payer: Payer::anonymous(),
            lines: [InvoiceLine::reconstitute(
                id: InvoiceLineId::fromString('01960000-0000-7000-8000-00000000c804'),
                label: 'Vermifuge',
                code: null,
                quantity: '1',
                enteredUnitPrice: Money::fromMinorUnits(1055, $eur),
                priceMode: PriceMode::INCLUDING_TAX,
                taxCategoryCode: 'veterinary.drug.companion',
                taxRatePercent: '5.5',
                operationCategory: OperationCategory::GOODS,
                source: InvoiceLineSource::CREDIT,
                sourceReference: '01960000-0000-7000-8000-00000000c805',
                amounts: LineAmounts::of(Money::fromMinorUnits(1000, $eur), Money::fromMinorUnits(1055, $eur), UnitPrice::fromMicroUnits(10_000_000, $eur)),
            )],
            breakdown: [RateBucket::of('5.5', 1000, 100, 50, $eur)],
            reason: 'Retour',
            refundMethod: null,
            refunded: Money::zero($eur),
            currency: $eur,
            now: new \DateTimeImmutable('2026-10-21 10:00:00'),
        );
    }
}
