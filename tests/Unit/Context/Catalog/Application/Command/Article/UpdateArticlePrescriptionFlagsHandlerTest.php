<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Article;

use App\Context\Catalog\Application\Command\Article\UpdateArticlePrescriptionFlags\UpdateArticlePrescriptionFlags;
use App\Context\Catalog\Application\Command\Article\UpdateArticlePrescriptionFlags\UpdateArticlePrescriptionFlagsHandler;
use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Article\ValueObject\DrugProperties;
use App\Context\Catalog\Domain\Article\ValueObject\PrescriptionClass;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateArticlePrescriptionFlagsHandler::class)]
#[CoversClass(UpdateArticlePrescriptionFlags::class)]
final class UpdateArticlePrescriptionFlagsHandlerTest extends TestCase
{
    private const string CLINIC_ID  = '01960000-0000-7000-8000-000000000a01';
    private const string ARTICLE_ID = '01960000-0000-7000-8000-000000000a02';

    public function testADrugTakesItsNewDeliveryRules(): void
    {
        $article = $this->drug(PrescriptionClass::RX, false);

        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->expects(self::once())->method('save')->with($article);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->handler($repository, $eventBus)(new UpdateArticlePrescriptionFlags(
            articleId: self::ARTICLE_ID,
            clinicId: self::CLINIC_ID,
            requiresPrescription: true,
            prescriptionClass: 'RX_CONTROLLED',
            isControlledSubstance: true,
        ));

        $properties = $article->drugProperties();

        self::assertNotNull($properties);
        self::assertTrue($properties->requiresPrescription());
        self::assertSame(PrescriptionClass::RX_CONTROLLED, $properties->prescriptionClass());
        self::assertTrue($properties->isControlledSubstance());
    }

    public function testTakingADrugOffPrescriptionClearsItsClass(): void
    {
        $article = $this->drug(PrescriptionClass::RX, false);

        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->expects(self::once())->method('save');

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new UpdateArticlePrescriptionFlags(
                articleId: self::ARTICLE_ID,
                clinicId: self::CLINIC_ID,
                requiresPrescription: false,
                prescriptionClass: null,
                isControlledSubstance: false,
            ),
        );

        $properties = $article->drugProperties();

        self::assertNotNull($properties);
        self::assertFalse($properties->requiresPrescription());
        self::assertNull($properties->prescriptionClass());
    }

    public function testWritingTheSameFlagsRecordsNothing(): void
    {
        $article = $this->drug(PrescriptionClass::RX, false);
        self::assertNotSame([], $article->pullDomainEvents());

        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->expects(self::once())->method('save');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::never())->method('publish');

        $this->handler($repository, $eventBus)(new UpdateArticlePrescriptionFlags(
            articleId: self::ARTICLE_ID,
            clinicId: self::CLINIC_ID,
            requiresPrescription: true,
            prescriptionClass: 'RX',
            isControlledSubstance: false,
        ));
    }

    public function testAnUnknownArticleIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ArticleNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new UpdateArticlePrescriptionFlags(
                articleId: self::ARTICLE_ID,
                clinicId: self::CLINIC_ID,
                requiresPrescription: true,
                prescriptionClass: 'RX',
                isControlledSubstance: false,
            ),
        );
    }

    public function testTheKindIsWhatDecidesWhetherFlagsApply(): void
    {
        self::assertSame(ArticleKind::DRUG, $this->drug(PrescriptionClass::RX, false)->kind());
    }

    private function handler(
        ArticleRepositoryInterface $repository,
        EventBusInterface $eventBus,
    ): UpdateArticlePrescriptionFlagsHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-22 10:00:00'));

        return new UpdateArticlePrescriptionFlagsHandler($repository, $clock, new DomainEventPublisher($eventBus));
    }

    private function drug(PrescriptionClass $class, bool $controlled): Article
    {
        return Article::createDrug(
            id: ArticleId::fromString(self::ARTICLE_ID),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: ArticleName::fromString('Amoxicilline 250'),
            code: ArticleCode::fromString('AMOX-250'),
            gtin: null,
            taxCategory: TaxCategoryCode::fromString('veterinary.drug.prescription'),
            basePrice: Money::fromMinorUnits(1250, CurrencyCode::fromString('EUR')),
            unitOfMeasure: UnitOfMeasure::fromString('UNIT'),
            drugProperties: DrugProperties::create(null, true, $class, $controlled),
            trackStock: true,
            createdAt: new \DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }
}
