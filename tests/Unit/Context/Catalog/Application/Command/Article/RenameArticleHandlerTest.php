<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Article;

use App\Context\Catalog\Application\Command\Article\RenameArticle\RenameArticle;
use App\Context\Catalog\Application\Command\Article\RenameArticle\RenameArticleHandler;
use App\Context\Catalog\Domain\Article\Event\ArticleRenamed;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenameArticleHandler::class)]
#[CoversClass(RenameArticle::class)]
final class RenameArticleHandlerTest extends TestCase
{
    public function testTheArticleTakesItsNewName(): void
    {
        $article = F::consumable();

        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->expects(self::once())->method('save')->with($article);

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ArticleRenamed::class))
        ;

        $this->handler($repository, $eventBus)(
            new RenameArticle(F::ARTICLE_ID, F::CLINIC_ID, 'Compresses non tissées'),
        );

        self::assertSame('Compresses non tissées', $article->name()->toString());
    }

    public function testAnUnknownArticleIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ArticleNotFoundException::class);

        $this->handler($repository, $this->createStub(EventBusInterface::class))(
            new RenameArticle(F::ARTICLE_ID, F::CLINIC_ID, 'Compresses non tissées'),
        );
    }

    private function handler(ArticleRepositoryInterface $repository, EventBusInterface $eventBus): RenameArticleHandler
    {
        return new RenameArticleHandler($repository, new FrozenClock(F::now()), new DomainEventPublisher($eventBus));
    }
}
