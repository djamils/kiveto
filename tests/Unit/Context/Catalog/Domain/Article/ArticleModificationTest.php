<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Domain\Article;

use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\Event\ArticleBasePriceChanged;
use App\Context\Catalog\Domain\Article\Event\ArticlePrescriptionFlagsUpdated;
use App\Context\Catalog\Domain\Article\Event\ArticleRenamed;
use App\Context\Catalog\Domain\Article\Event\ArticleRestored;
use App\Context\Catalog\Domain\Article\Event\ArticleTaxCategoryChanged;
use App\Context\Catalog\Domain\Article\Exception\ArchivedArticleCannotBeModifiedException;
use App\Context\Catalog\Domain\Article\Exception\InvalidDrugPropertiesException;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Article::class)]
final class ArticleModificationTest extends TestCase
{
    public function testAReconstitutedArticleExposesItsState(): void
    {
        $article = F::consumable();

        self::assertSame(F::ARTICLE_ID, $article->id()->toString());
        self::assertSame(F::CLINIC_ID, $article->clinicId()->toString());
        self::assertSame('Compresses stériles', $article->name()->toString());
        self::assertSame('COMP-01', $article->code()->toString());
        self::assertSame(ArticleKind::CONSUMABLE, $article->kind());
        self::assertNull($article->gtin());
        self::assertSame('veterinary.consumable', $article->taxCategory()->toString());
        self::assertSame(350, $article->basePrice()->minorUnits());
        self::assertSame('UNIT', $article->unitOfMeasure()->toString());
        self::assertNull($article->drugProperties());
        self::assertTrue($article->trackStock());
        self::assertSame(ArticleStatus::ACTIVE, $article->status());
        self::assertSame('2026-01-01 10:00:00', $article->createdAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-01 10:00:00', $article->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testRenamingRepricingAndRetaxingAreRecorded(): void
    {
        $article = F::consumable();

        $article->rename(ArticleName::fromString('Compresses non tissées'), F::now());
        $article->changeBasePrice(F::eur(420), F::now());
        $article->changeTaxCategory(TaxCategoryCode::fromString('veterinary.food'), F::now());

        self::assertSame('Compresses non tissées', $article->name()->toString());
        self::assertSame(420, $article->basePrice()->minorUnits());
        self::assertSame('veterinary.food', $article->taxCategory()->toString());
        self::assertSame(F::now(), $article->updatedAt());

        $events = $article->pullDomainEvents();
        self::assertCount(3, $events);
        self::assertInstanceOf(ArticleRenamed::class, $events[0]);
        self::assertInstanceOf(ArticleBasePriceChanged::class, $events[1]);
        self::assertInstanceOf(ArticleTaxCategoryChanged::class, $events[2]);
    }

    public function testAnArchivedArticleCannotBeRenamed(): void
    {
        $this->expectException(ArchivedArticleCannotBeModifiedException::class);

        F::consumable(ArticleStatus::ARCHIVED)->rename(ArticleName::fromString('Autre nom'), F::now());
    }

    public function testAnArchivedArticleCannotBeRepriced(): void
    {
        $this->expectException(ArchivedArticleCannotBeModifiedException::class);

        F::consumable(ArticleStatus::ARCHIVED)->changeBasePrice(F::eur(420), F::now());
    }

    public function testAnArchivedArticleCannotBeRetaxed(): void
    {
        $this->expectException(ArchivedArticleCannotBeModifiedException::class);

        F::consumable(ArticleStatus::ARCHIVED)->changeTaxCategory(
            TaxCategoryCode::fromString('veterinary.food'),
            F::now(),
        );
    }

    public function testRestoringIsRecordedOnceAndIsIdempotent(): void
    {
        $article = F::consumable(ArticleStatus::ARCHIVED);

        $article->restore(F::now());
        $article->restore(F::now());

        self::assertSame(ArticleStatus::ACTIVE, $article->status());

        $events = $article->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ArticleRestored::class, $events[0]);
    }

    public function testArchivingTwiceIsRecordedOnce(): void
    {
        $article = F::consumable();

        $article->archive(F::now());
        $article->archive(F::now());

        self::assertCount(1, $article->pullDomainEvents());
    }

    public function testADrugTakesNewPrescriptionFlagsAndKeepsItsAuthorization(): void
    {
        $article = F::drug(ArticleStatus::ACTIVE, 'FR/V/1234567');

        $article->updatePrescriptionFlags(true, PrescriptionClass::RX_CONTROLLED, true, F::now());

        $properties = $article->drugProperties();
        self::assertNotNull($properties);
        self::assertSame('FR/V/1234567', $properties->authorizationRef()?->toString());
        self::assertSame(PrescriptionClass::RX_CONTROLLED, $properties->prescriptionClass());
        self::assertTrue($properties->isControlledSubstance());
        self::assertSame(F::now(), $article->updatedAt());

        $events = $article->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ArticlePrescriptionFlagsUpdated::class, $events[0]);
    }

    public function testRewritingTheSamePrescriptionFlagsRecordsNothing(): void
    {
        $article = F::drug(ArticleStatus::ACTIVE, null);

        $article->updatePrescriptionFlags(true, PrescriptionClass::RX, false, F::now());

        self::assertSame([], $article->pullDomainEvents());
    }

    public function testANonDrugArticleHasNoPrescriptionFlags(): void
    {
        $this->expectException(InvalidDrugPropertiesException::class);

        F::consumable()->updatePrescriptionFlags(true, PrescriptionClass::RX, false, F::now());
    }
}
