<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Consultation\Domain\ValueObject;

use App\Context\Consultation\Domain\ValueObject\PrescriptionLineRecord;
use App\Context\Consultation\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PrescriptionLineRecordTest extends TestCase
{
    private const string USER_ID    = '55555555-5555-4555-8555-555555555555';
    private const string ARTICLE_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

    public function testCreateTrimsAndKeepsAllFields(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-20 15:00:00');

        $line = PrescriptionLineRecord::create(
            '  ' . self::ARTICLE_ID . '  ',
            '  MED-01  ',
            '  Amoxicilline 250 mg  ',
            '  1 cp  ',
            '  2 fois par jour  ',
            7,
            '  Orale  ',
            14.0,
            250,
            'EUR',
            '  TVA20  ',
            $createdAt,
            UserId::fromString(self::USER_ID),
        );

        self::assertNotSame('', $line->getId());
        self::assertSame(self::ARTICLE_ID, $line->getArticleId());
        self::assertSame('MED-01', $line->getCode());
        self::assertSame('Amoxicilline 250 mg', $line->getLabel());
        self::assertSame('1 cp', $line->getDose());
        self::assertSame('2 fois par jour', $line->getFrequency());
        self::assertSame(7, $line->getDurationDays());
        self::assertSame('Orale', $line->getRoute());
        self::assertSame(14.0, $line->getQuantity());
        self::assertSame(250, $line->getUnitPriceMinorUnits());
        self::assertSame('EUR', $line->getCurrency());
        self::assertSame('TVA20', $line->getTaxCategoryCode());
        self::assertSame($createdAt, $line->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $line->getCreatedByUserId());
    }

    public function testCreateNormalizesNullOptionalFields(): void
    {
        $line = self::createMinimalLine();

        self::assertNull($line->getArticleId());
        self::assertNull($line->getCode());
        self::assertNull($line->getDose());
        self::assertNull($line->getFrequency());
        self::assertNull($line->getDurationDays());
        self::assertNull($line->getRoute());
        self::assertNull($line->getTaxCategoryCode());
    }

    public function testCreateNormalizesBlankOptionalFieldsToNull(): void
    {
        $line = PrescriptionLineRecord::create(
            '   ',
            '   ',
            'Amoxicilline',
            '   ',
            '   ',
            null,
            '   ',
            1.0,
            250,
            'EUR',
            '   ',
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertNull($line->getArticleId());
        self::assertNull($line->getCode());
        self::assertNull($line->getDose());
        self::assertNull($line->getFrequency());
        self::assertNull($line->getRoute());
        self::assertNull($line->getTaxCategoryCode());
    }

    public function testCreateAcceptsZeroPrice(): void
    {
        $line = PrescriptionLineRecord::create(
            null,
            null,
            'Échantillon',
            null,
            null,
            null,
            null,
            1.0,
            0,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );

        self::assertSame(0, $line->getUnitPriceMinorUnits());
    }

    public function testCreateRejectsEmptyLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription label cannot be empty');

        self::createWithLabel('   ');
    }

    public function testCreateRejectsTooLongLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription label cannot exceed 255 characters');

        self::createWithLabel(str_repeat('l', 256));
    }

    public function testCreateRejectsNonPositiveQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription quantity must be positive');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            null,
            null,
            null,
            0.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsQuantityRoundingToZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription quantity must be positive');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            null,
            null,
            null,
            0.002,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsQuantityOverflowingTheColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription quantity cannot exceed 99999.99');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            null,
            null,
            null,
            100000.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsNonPositiveDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription duration must be at least 1 day');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            null,
            0,
            null,
            1.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsNegativePrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription price cannot be negative');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            null,
            null,
            null,
            1.0,
            -1,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsBlankCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription price requires a currency');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            null,
            null,
            null,
            1.0,
            250,
            '   ',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongArticleId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription article id cannot exceed 36 characters');

        self::createWithArticleId(str_repeat('a', 37));
    }

    public function testCreateRejectsMalformedArticleId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription article id must be a valid UUID');

        self::createWithArticleId('not-a-valid-uuid');
    }

    public function testCreateRejectsTooLongCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription code cannot exceed 40 characters');

        PrescriptionLineRecord::create(
            null,
            str_repeat('c', 41),
            'Amoxicilline',
            null,
            null,
            null,
            null,
            1.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongDose(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription dose cannot exceed 60 characters');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            str_repeat('d', 61),
            null,
            null,
            null,
            1.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongFrequency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription frequency cannot exceed 60 characters');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            str_repeat('f', 61),
            null,
            null,
            1.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongRoute(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription route cannot exceed 60 characters');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            null,
            null,
            str_repeat('r', 61),
            1.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testCreateRejectsTooLongTaxCategoryCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prescription tax category code cannot exceed 40 characters');

        PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            null,
            null,
            null,
            1.0,
            250,
            'EUR',
            str_repeat('t', 41),
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    public function testReconstituteKeepsProvidedValues(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-20 15:00:00');

        $line = self::reconstituteLine($createdAt);

        self::assertSame('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', $line->getId());
        self::assertSame(self::ARTICLE_ID, $line->getArticleId());
        self::assertSame('MED-77', $line->getCode());
        self::assertSame('Méloxicam', $line->getLabel());
        self::assertSame('0.5 mL', $line->getDose());
        self::assertSame('1 fois par jour', $line->getFrequency());
        self::assertSame(5, $line->getDurationDays());
        self::assertSame('Orale', $line->getRoute());
        self::assertSame(5.0, $line->getQuantity());
        self::assertSame(1250, $line->getUnitPriceMinorUnits());
        self::assertSame('EUR', $line->getCurrency());
        self::assertSame('TVA20', $line->getTaxCategoryCode());
        self::assertSame($createdAt, $line->getCreatedAtUtc());
        self::assertSame(self::USER_ID, $line->getCreatedByUserId());
    }

    public function testPosologySummaryJoinsEveryPart(): void
    {
        $line = self::reconstituteLine(new \DateTimeImmutable('2026-08-20 15:00:00'));

        self::assertSame('0.5 mL · 1 fois par jour · 5 j · Orale', $line->getPosologySummary());
    }

    public function testPosologySummarySkipsMissingParts(): void
    {
        $line = PrescriptionLineRecord::reconstitute(
            id: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            articleId: null,
            code: null,
            label: 'Méloxicam',
            dose: '0.5 mL',
            frequency: null,
            durationDays: null,
            route: 'Orale',
            quantity: 1.0,
            unitPriceMinorUnits: 1250,
            currency: 'EUR',
            taxCategoryCode: null,
            createdAtUtc: new \DateTimeImmutable('2026-08-20 15:00:00'),
            createdByUserId: self::USER_ID,
        );

        self::assertSame('0.5 mL · Orale', $line->getPosologySummary());
    }

    public function testPosologySummaryIsEmptyWithoutAnyPart(): void
    {
        $line = self::createMinimalLine();

        self::assertSame('', $line->getPosologySummary());
    }

    private static function createMinimalLine(): PrescriptionLineRecord
    {
        return PrescriptionLineRecord::create(
            null,
            null,
            'Amoxicilline',
            null,
            null,
            null,
            null,
            1.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    private static function createWithLabel(string $label): PrescriptionLineRecord
    {
        return PrescriptionLineRecord::create(
            null,
            null,
            $label,
            null,
            null,
            null,
            null,
            1.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    private static function createWithArticleId(string $articleId): PrescriptionLineRecord
    {
        return PrescriptionLineRecord::create(
            $articleId,
            null,
            'Amoxicilline',
            null,
            null,
            null,
            null,
            1.0,
            250,
            'EUR',
            null,
            new \DateTimeImmutable('2026-08-20 15:00:00'),
            UserId::fromString(self::USER_ID),
        );
    }

    private static function reconstituteLine(\DateTimeImmutable $createdAt): PrescriptionLineRecord
    {
        return PrescriptionLineRecord::reconstitute(
            id: 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
            articleId: self::ARTICLE_ID,
            code: 'MED-77',
            label: 'Méloxicam',
            dose: '0.5 mL',
            frequency: '1 fois par jour',
            durationDays: 5,
            route: 'Orale',
            quantity: 5.0,
            unitPriceMinorUnits: 1250,
            currency: 'EUR',
            taxCategoryCode: 'TVA20',
            createdAtUtc: $createdAt,
            createdByUserId: self::USER_ID,
        );
    }
}
