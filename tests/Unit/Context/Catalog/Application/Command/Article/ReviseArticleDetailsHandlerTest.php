<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Article;

use App\Context\Catalog\Application\Command\Article\ReviseArticleDetails\ReviseArticleDetails;
use App\Context\Catalog\Application\Command\Article\ReviseArticleDetails\ReviseArticleDetailsHandler;
use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\Event\ArticleDetailsRevised;
use App\Context\Catalog\Domain\Article\Exception\ArchivedArticleCannotBeModifiedException;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Exception\DuplicateGtinException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleCode;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleId;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleKind;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleName;
use App\Context\Catalog\Domain\Article\ValueObject\Gtin;
use App\Context\Catalog\Domain\ValueObject\CatalogPrice;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\UnitOfMeasure\Service\UnitOfMeasureRegistry;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitCategory;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitDefinition;
use App\Shared\Domain\UnitOfMeasure\ValueObject\UnitOfMeasure;
use App\Shared\Domain\ValueObject\CurrencyCode;
use App\Shared\Money\Domain\ValueObject\Money;
use App\System\Taxation\Domain\ValueObject\TaxCategoryCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReviseArticleDetailsHandler::class)]
#[CoversClass(ReviseArticleDetails::class)]
#[CoversClass(ArticleDetailsRevised::class)]
#[CoversClass(Article::class)]
final class ReviseArticleDetailsHandlerTest extends TestCase
{
    private const string CLINIC_ID  = '01960000-0000-7000-8000-000000000c01';
    private const string ARTICLE_ID = '01960000-0000-7000-8000-000000000c02';
    private const string OTHER_ID   = '01960000-0000-7000-8000-000000000c03';
    private const string GTIN       = '03661103046394';

    public function testAnArticleTakesItsBarcodeUnitAndStockFollowUp(): void
    {
        $article = $this->article(null, 'UNIT', true, self::ARTICLE_ID);
        self::assertNotSame([], $article->pullDomainEvents());

        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->method('findByGtin')->willReturn(null);
        $repository->expects(self::once())->method('save')->with($article);

        $published = [];
        $eventBus  = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')->willReturnCallback(
            static function (array $stamps, object ...$events) use (&$published): void {
                array_push($published, ...$events);
            },
        );

        $units = $this->createMock(UnitOfMeasureRegistry::class);
        $units->expects(self::once())->method('resolve')
            ->willReturn(new UnitDefinition(UnitOfMeasure::fromString('BOX'), UnitCategory::COUNT, null))
        ;

        $this->handler($repository, $eventBus, $units)(new ReviseArticleDetails(
            articleId: self::ARTICLE_ID,
            clinicId: self::CLINIC_ID,
            gtin: self::GTIN,
            unitOfMeasure: 'BOX',
            trackStock: false,
        ));

        self::assertSame(self::GTIN, $article->gtin()?->toString());
        self::assertSame('BOX', $article->unitOfMeasure()->toString());
        self::assertFalse($article->trackStock());

        $event = $published[0];
        self::assertInstanceOf(ArticleDetailsRevised::class, $event);
        self::assertSame(self::ARTICLE_ID, $event->aggregateId());
        self::assertSame([
            'articleId'     => self::ARTICLE_ID,
            'clinicId'      => self::CLINIC_ID,
            'gtin'          => self::GTIN,
            'unitOfMeasure' => 'BOX',
            'trackStock'    => false,
        ], $event->payload());
    }

    public function testItsOwnBarcodeIsNotADuplicate(): void
    {
        $article = $this->article(self::GTIN, 'UNIT', true, self::ARTICLE_ID);
        self::assertNotSame([], $article->pullDomainEvents());

        $repository = $this->createStub(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->method('findByGtin')->willReturn($article);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish');

        $this->handler($repository, $eventBus)(new ReviseArticleDetails(
            articleId: self::ARTICLE_ID,
            clinicId: self::CLINIC_ID,
            gtin: self::GTIN,
            unitOfMeasure: 'UNIT',
            trackStock: false,
        ));

        self::assertFalse($article->trackStock());
    }

    public function testTheSameDetailsRecordNothing(): void
    {
        foreach ([null, self::GTIN] as $gtin) {
            $article = $this->article($gtin, 'UNIT', true, self::ARTICLE_ID);
            self::assertNotSame([], $article->pullDomainEvents());

            $repository = $this->createStub(ArticleRepositoryInterface::class);
            $repository->method('findById')->willReturn($article);
            $repository->method('findByGtin')->willReturn($article);

            $eventBus = $this->createMock(EventBusInterface::class);
            $eventBus->expects(self::never())->method('publish');

            $this->handler($repository, $eventBus)(new ReviseArticleDetails(
                articleId: self::ARTICLE_ID,
                clinicId: self::CLINIC_ID,
                gtin: $gtin,
                unitOfMeasure: 'UNIT',
                trackStock: true,
            ));
        }
    }

    public function testRemovingTheBarcodeIsARevision(): void
    {
        $article = $this->article(self::GTIN, 'UNIT', true, self::ARTICLE_ID);
        self::assertNotSame([], $article->pullDomainEvents());

        $article->reviseDetails(null, UnitOfMeasure::fromString('UNIT'), true, new \DateTimeImmutable('2026-08-22'));

        self::assertNull($article->gtin());
        self::assertCount(1, $article->pullDomainEvents());
    }

    public function testABarcodeHeldByAnotherArticleIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($this->article(null, 'UNIT', true, self::ARTICLE_ID));
        $repository->method('findByGtin')->willReturn($this->article(self::GTIN, 'UNIT', true, self::OTHER_ID));
        $repository->expects(self::never())->method('save');

        $this->expectException(DuplicateGtinException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new ReviseArticleDetails(
            articleId: self::ARTICLE_ID,
            clinicId: self::CLINIC_ID,
            gtin: self::GTIN,
            unitOfMeasure: 'UNIT',
            trackStock: true,
        ));
    }

    public function testAnArchivedArticleCannotBeRevised(): void
    {
        $article = $this->article(null, 'UNIT', true, self::ARTICLE_ID);
        $article->archive(new \DateTimeImmutable('2026-08-01'));

        $repository = $this->createStub(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);

        $this->expectException(ArchivedArticleCannotBeModifiedException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new ReviseArticleDetails(
            articleId: self::ARTICLE_ID,
            clinicId: self::CLINIC_ID,
            gtin: null,
            unitOfMeasure: 'BOX',
            trackStock: true,
        ));
    }

    public function testAnUnknownArticleIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ArticleNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(new ReviseArticleDetails(
            articleId: self::ARTICLE_ID,
            clinicId: self::CLINIC_ID,
            gtin: null,
            unitOfMeasure: 'UNIT',
            trackStock: true,
        ));
    }

    private function handler(
        ArticleRepositoryInterface $repository,
        EventBusInterface $eventBus,
        ?UnitOfMeasureRegistry $units = null,
    ): ReviseArticleDetailsHandler {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-08-22 10:00:00'));

        return new ReviseArticleDetailsHandler(
            $repository,
            $units ?? $this->knownUnits(),
            $clock,
            new DomainEventPublisher($eventBus),
        );
    }

    private function knownUnits(): UnitOfMeasureRegistry
    {
        $units = $this->createStub(UnitOfMeasureRegistry::class);
        $units->method('resolve')->willReturn(new UnitDefinition(UnitOfMeasure::fromString('UNIT'), UnitCategory::COUNT, null));

        return $units;
    }

    private function article(?string $gtin, string $unit, bool $trackStock, string $id): Article
    {
        return Article::createNonDrug(
            id: ArticleId::fromString($id),
            clinicId: ClinicId::fromString(self::CLINIC_ID),
            name: ArticleName::fromString('Collerette élisabéthaine'),
            code: ArticleCode::fromString('COLLAR-E'),
            kind: ArticleKind::CONSUMABLE,
            gtin: null !== $gtin ? Gtin::fromString($gtin) : null,
            taxCategory: TaxCategoryCode::fromString('veterinary.consumable.medical'),
            basePrice: CatalogPrice::includingTax(Money::fromMinorUnits(667, CurrencyCode::fromString('EUR'))),
            unitOfMeasure: UnitOfMeasure::fromString($unit),
            trackStock: $trackStock,
            createdAt: new \DateTimeImmutable('2026-01-01 10:00:00'),
        );
    }
}
