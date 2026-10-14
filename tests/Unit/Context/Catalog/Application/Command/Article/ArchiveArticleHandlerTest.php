<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Article;

use App\Context\Catalog\Application\Command\Article\ArchiveArticle\ArchiveArticle;
use App\Context\Catalog\Application\Command\Article\ArchiveArticle\ArchiveArticleHandler;
use App\Context\Catalog\Domain\Article\Event\ArticleArchived;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchiveArticleHandler::class)]
#[CoversClass(ArchiveArticle::class)]
final class ArchiveArticleHandlerTest extends TestCase
{
    public function testAnActiveArticleIsArchivedSavedAndAnnounced(): void
    {
        $article = F::consumable();

        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->expects(self::once())->method('save')->with($article);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ArticleArchived::class))
        ;

        $this->handler($repository, $eventBus)(new ArchiveArticle(F::ARTICLE_ID, F::CLINIC_ID));

        self::assertSame(ArticleStatus::ARCHIVED, $article->status());
    }

    public function testAnUnknownArticleIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ArticleNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new ArchiveArticle(F::ARTICLE_ID, F::CLINIC_ID),
        );
    }

    private function handler(ArticleRepositoryInterface $repository, EventBusInterface $eventBus): ArchiveArticleHandler
    {
        return new ArchiveArticleHandler($repository, new FrozenClock(F::now()), new DomainEventPublisher($eventBus));
    }
}
