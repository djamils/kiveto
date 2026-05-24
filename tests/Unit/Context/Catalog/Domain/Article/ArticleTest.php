<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\Article;

use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\Event\ArticleCreated;
use App\Context\Catalog\Domain\Article\Exception\ArchivedArticleCannotBeModifiedException;
use App\Context\Catalog\Domain\Article\Exception\InvalidDrugPropertiesException;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Article\ValueObject\DrugProperties;
use App\Context\Catalog\Domain\Article\ValueObject\Gtin;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\TestCase;

final class ArticleTest extends TestCase
{
    public function testCreateDrugRecordsEvent(): void
    {
        $now     = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $article = Article::createDrug(
            id: ArticleId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: ArticleName::fromString('Amoxicilline'),
            code: ArticleCode::fromString('AMOX'),
            gtin: null,
            taxCategory: TaxCategoryCode::fromString('veterinary.drug.prescription'),
            basePrice: Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')),
            unitOfMeasure: UnitOfMeasure::fromString('TABLET'),
            drugProperties: $this->makeDrugProperties(),
            trackStock: true,
            createdAt: $now,
        );

        $events = $article->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ArticleCreated::class, $events[0]);
        self::assertSame('DRUG', $events[0]->kind);
        self::assertSame('AMOX', $events[0]->code);
    }

    public function testCreateNonDrugThrowsWhenKindIsDrug(): void
    {
        $now = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');

        $this->expectException(InvalidDrugPropertiesException::class);

        Article::createNonDrug(
            id: ArticleId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: ArticleName::fromString('Test'),
            code: ArticleCode::fromString('TEST'),
            kind: ArticleKind::DRUG,
            gtin: null,
            taxCategory: TaxCategoryCode::fromString('veterinary.drug.prescription'),
            basePrice: Money::fromMinorUnits(1000, CurrencyCode::fromString('EUR')),
            unitOfMeasure: UnitOfMeasure::fromString('UNIT'),
            trackStock: false,
            createdAt: $now,
        );
    }

    public function testUpdatePrescriptionFlagsIdempotent(): void
    {
        $now     = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $article = $this->makeDrugArticle();

        // Same flags — should be no-op
        $article->updatePrescriptionFlags(
            requiresPrescription: true,
            prescriptionClass: PrescriptionClass::RX,
            isControlledSubstance: false,
            updatedAt: $now,
        );

        self::assertSame([], $article->pullDomainEvents());
    }

    public function testArchiveIdempotent(): void
    {
        $now     = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $article = $this->makeDrugArticle();

        $article->archive($now);
        $article->archive($now);

        $events = $article->pullDomainEvents();
        self::assertCount(1, $events);
    }

    public function testRestoreIdempotent(): void
    {
        $now     = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $article = $this->makeDrugArticle();

        $article->restore($now);
        $article->restore($now);

        self::assertSame([], $article->pullDomainEvents());
    }

    public function testDrugPropertiesWithUpdatedFlags(): void
    {
        $props = DrugProperties::create(
            authorizationRef: null,
            requiresPrescription: false,
            prescriptionClass: null,
            isControlledSubstance: false,
        );

        $updated = $props->withUpdatedFlags(
            requiresPrescription: true,
            prescriptionClass: PrescriptionClass::RX_CONTROLLED,
            isControlledSubstance: true,
        );

        self::assertTrue($updated->requiresPrescription());
        self::assertSame(PrescriptionClass::RX_CONTROLLED, $updated->prescriptionClass());
        self::assertTrue($updated->isControlledSubstance());
        // Original unchanged
        self::assertFalse($props->requiresPrescription());
    }

    public function testRenameThrowsWhenArchived(): void
    {
        $now     = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $article = $this->makeDrugArticle();
        $article->archive($now);
        $_ = $article->pullDomainEvents();

        $this->expectException(ArchivedArticleCannotBeModifiedException::class);

        $article->rename(ArticleName::fromString('New Name'), $now);
    }

    private function makeDrugProperties(): DrugProperties
    {
        return DrugProperties::create(
            authorizationRef: null,
            requiresPrescription: true,
            prescriptionClass: PrescriptionClass::RX,
            isControlledSubstance: false,
        );
    }

    private function makeDrugArticle(): Article
    {
        $now     = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $article = Article::createDrug(
            id: ArticleId::fromString('01950000-0000-7000-0000-000000000001'),
            clinicId: ClinicId::fromString('01950000-0000-7000-0000-000000000002'),
            name: ArticleName::fromString('Amoxicilline 250mg'),
            code: ArticleCode::fromString('AMOX-250'),
            gtin: Gtin::fromString('03401234567890'),
            taxCategory: TaxCategoryCode::fromString('veterinary.drug.prescription'),
            basePrice: Money::fromMinorUnits(1500, CurrencyCode::fromString('EUR')),
            unitOfMeasure: UnitOfMeasure::fromString('TABLET'),
            drugProperties: $this->makeDrugProperties(),
            trackStock: true,
            createdAt: $now,
        );
        $_ = $article->pullDomainEvents();

        return $article;
    }
}
