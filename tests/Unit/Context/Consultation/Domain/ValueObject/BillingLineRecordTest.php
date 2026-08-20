<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\BillingLineRecord;
use App\Context\Consultation\Domain\ValueObject\BillingLineSource;
use PHPUnit\Framework\TestCase;

final class BillingLineRecordTest extends TestCase
{
    private const string SOURCE_LINE_ID = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

    public function testCreateTrimsLabelAndKeepsAllFields(): void
    {
        $line = BillingLineRecord::create(
            self::SOURCE_LINE_ID,
            BillingLineSource::PLAN_ACT,
            '  Détartrage  ',
            'ACT-42',
            2.0,
            9900,
            'EUR',
            'TVA20',
        );

        self::assertNotSame('', $line->getId());
        self::assertSame(self::SOURCE_LINE_ID, $line->getSourceLineId());
        self::assertSame(BillingLineSource::PLAN_ACT, $line->getSource());
        self::assertSame('Détartrage', $line->getLabel());
        self::assertSame('ACT-42', $line->getCode());
        self::assertSame(2.0, $line->getQuantity());
        self::assertSame(9900, $line->getUnitPriceMinorUnits());
        self::assertSame('EUR', $line->getCurrency());
        self::assertSame('TVA20', $line->getTaxCategoryCode());
    }

    public function testCreateAcceptsNullCodeAndTaxCategoryAndZeroPrice(): void
    {
        $line = BillingLineRecord::create(
            self::SOURCE_LINE_ID,
            BillingLineSource::PRESCRIPTION,
            'Échantillon',
            null,
            1.0,
            0,
            'EUR',
            null,
        );

        self::assertNull($line->getCode());
        self::assertNull($line->getTaxCategoryCode());
        self::assertSame(0, $line->getUnitPriceMinorUnits());
        self::assertSame(BillingLineSource::PRESCRIPTION, $line->getSource());
    }

    public function testCreateRejectsEmptyLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing line label cannot be empty');

        BillingLineRecord::create(
            self::SOURCE_LINE_ID,
            BillingLineSource::PLAN_ACT,
            '   ',
            null,
            1.0,
            9900,
            'EUR',
            null,
        );
    }

    public function testCreateRejectsNonPositiveQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing line quantity must be positive');

        BillingLineRecord::create(
            self::SOURCE_LINE_ID,
            BillingLineSource::PLAN_ACT,
            'Détartrage',
            null,
            0.0,
            9900,
            'EUR',
            null,
        );
    }

    public function testCreateRejectsQuantityRoundingToZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing line quantity must be positive');

        BillingLineRecord::create(
            self::SOURCE_LINE_ID,
            BillingLineSource::PLAN_ACT,
            'Détartrage',
            null,
            0.001,
            9900,
            'EUR',
            null,
        );
    }

    public function testCreateRejectsNegativePrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing line price cannot be negative');

        BillingLineRecord::create(
            self::SOURCE_LINE_ID,
            BillingLineSource::PLAN_ACT,
            'Détartrage',
            null,
            1.0,
            -1,
            'EUR',
            null,
        );
    }

    public function testCreateRejectsBlankCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing line price requires a currency');

        BillingLineRecord::create(
            self::SOURCE_LINE_ID,
            BillingLineSource::PLAN_ACT,
            'Détartrage',
            null,
            1.0,
            9900,
            '   ',
            null,
        );
    }

    public function testReconstituteKeepsProvidedValues(): void
    {
        $line = self::reconstituteLine();

        self::assertSame('dddddddd-dddd-4ddd-8ddd-dddddddddddd', $line->getId());
        self::assertSame(self::SOURCE_LINE_ID, $line->getSourceLineId());
        self::assertSame(BillingLineSource::PRESCRIPTION, $line->getSource());
        self::assertSame('Méloxicam', $line->getLabel());
        self::assertSame('MED-77', $line->getCode());
        self::assertSame(2.0, $line->getQuantity());
        self::assertSame(1250, $line->getUnitPriceMinorUnits());
        self::assertSame('EUR', $line->getCurrency());
        self::assertSame('TVA20', $line->getTaxCategoryCode());
    }

    public function testWithSourceDetailsPreservesIdAndPriceSnapshot(): void
    {
        $line = self::reconstituteLine();

        $updated = $line->withSourceDetails('  Méloxicam 1,5 mg  ', 'MED-78', 3.0);

        self::assertNotSame($line, $updated);
        self::assertSame('Méloxicam 1,5 mg', $updated->getLabel());
        self::assertSame('MED-78', $updated->getCode());
        self::assertSame(3.0, $updated->getQuantity());
        self::assertSame($line->getId(), $updated->getId());
        self::assertSame(self::SOURCE_LINE_ID, $updated->getSourceLineId());
        self::assertSame(BillingLineSource::PRESCRIPTION, $updated->getSource());
        self::assertSame(1250, $updated->getUnitPriceMinorUnits());
        self::assertSame('EUR', $updated->getCurrency());
        self::assertSame('TVA20', $updated->getTaxCategoryCode());
        self::assertSame(2.0, $line->getQuantity());
    }

    public function testWithSourceDetailsAcceptsNullCode(): void
    {
        $line = self::reconstituteLine();

        $updated = $line->withSourceDetails('Méloxicam', null, 1.0);

        self::assertNull($updated->getCode());
    }

    public function testWithSourceDetailsRejectsEmptyLabel(): void
    {
        $line = self::reconstituteLine();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing line label cannot be empty');

        $line->withSourceDetails('   ', null, 1.0);
    }

    public function testWithSourceDetailsRejectsNonPositiveQuantity(): void
    {
        $line = self::reconstituteLine();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing line quantity must be positive');

        $line->withSourceDetails('Méloxicam', null, -1.0);
    }

    public function testWithSourceDetailsRejectsQuantityRoundingToZero(): void
    {
        $line = self::reconstituteLine();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Billing line quantity must be positive');

        $line->withSourceDetails('Méloxicam', null, 0.004);
    }

    public function testTotalMinorUnitsMultipliesPriceByQuantity(): void
    {
        $line = self::reconstituteLine();

        self::assertSame(2500, $line->getTotalMinorUnits());
    }

    public function testTotalMinorUnitsRoundsHalfUp(): void
    {
        $line = BillingLineRecord::reconstitute(
            id: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            sourceLineId: self::SOURCE_LINE_ID,
            source: BillingLineSource::PRESCRIPTION,
            label: 'Méloxicam',
            code: null,
            quantity: 0.5,
            unitPriceMinorUnits: 333,
            currency: 'EUR',
            taxCategoryCode: null,
        );

        self::assertSame(167, $line->getTotalMinorUnits());
    }

    private static function reconstituteLine(): BillingLineRecord
    {
        return BillingLineRecord::reconstitute(
            id: 'dddddddd-dddd-4ddd-8ddd-dddddddddddd',
            sourceLineId: self::SOURCE_LINE_ID,
            source: BillingLineSource::PRESCRIPTION,
            label: 'Méloxicam',
            code: 'MED-77',
            quantity: 2.0,
            unitPriceMinorUnits: 1250,
            currency: 'EUR',
            taxCategoryCode: 'TVA20',
        );
    }
}
