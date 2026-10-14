<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Article;

use App\Context\Catalog\Application\Command\Article\ChangeArticleTaxCategory\ChangeArticleTaxCategory;
use App\Context\Catalog\Application\Command\Article\ChangeArticleTaxCategory\ChangeArticleTaxCategoryHandler;
use App\Context\Catalog\Domain\Article\Event\ArticleTaxCategoryChanged;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\System\Taxation\Domain\Service\TaxCategoryRegistryInterface;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeArticleTaxCategoryHandler::class)]
#[CoversClass(ChangeArticleTaxCategory::class)]
final class ChangeArticleTaxCategoryHandlerTest extends TestCase
{
    public function testTheArticleMovesToTheNewTaxCategory(): void
    {
        $article = F::consumable();

        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->expects(self::once())->method('save')->with($article);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ArticleTaxCategoryChanged::class))
        ;

        $this->handler($repository, $eventBus, true)(
            new ChangeArticleTaxCategory(F::ARTICLE_ID, F::CLINIC_ID, 'veterinary.food'),
        );

        self::assertSame('veterinary.food', $article->taxCategory()->toString());
    }

    public function testAnUnknownTaxCategoryIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->expects(self::never())->method('findById');
        $repository->expects(self::never())->method('save');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Unknown tax category code: "veterinary.unknown".');

        $this->handler($repository, $this->createStub(EventBusInterface::class), false)(
            new ChangeArticleTaxCategory(F::ARTICLE_ID, F::CLINIC_ID, 'veterinary.unknown'),
        );
    }

    public function testAnUnknownArticleIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ArticleNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class), true)(
            new ChangeArticleTaxCategory(F::ARTICLE_ID, F::CLINIC_ID, 'veterinary.food'),
        );
    }

    private function handler(
        ArticleRepositoryInterface $repository,
        EventBusInterface $eventBus,
        bool $taxCategoryKnown,
    ): ChangeArticleTaxCategoryHandler {
        $registry = $this->createStub(TaxCategoryRegistryInterface::class);
        $registry->method('has')->willReturn($taxCategoryKnown);

        return new ChangeArticleTaxCategoryHandler(
            $repository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
            $registry,
        );
    }
}
