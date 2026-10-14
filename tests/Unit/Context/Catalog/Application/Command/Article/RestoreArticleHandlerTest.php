<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Catalog\Application\Command\Article;

use App\Context\Catalog\Application\Command\Article\RestoreArticle\RestoreArticle;
use App\Context\Catalog\Application\Command\Article\RestoreArticle\RestoreArticleHandler;
use App\Context\Catalog\Application\Port\PharmaceuticalRefProviderInterface;
use App\Context\Catalog\Domain\Article\Article;
use App\Context\Catalog\Domain\Article\Event\ArticleRestored;
use App\Context\Catalog\Domain\Article\Exception\ArticleNotFoundException;
use App\Context\Catalog\Domain\Article\Exception\RegulatoryRestoreForbiddenException;
use App\Context\Catalog\Domain\Article\Repository\ArticleRepositoryInterface;
use App\Context\Catalog\Domain\Article\ValueObject\ArticleStatus;
use App\Context\Catalog\Infrastructure\Adapter\HealthRegistry\PharmaceuticalRef;
use App\Shared\Application\Bus\EventBusInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\Context\Catalog\Application\Command\CatalogCommandFixtures as F;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RestoreArticleHandler::class)]
#[CoversClass(RestoreArticle::class)]
final class RestoreArticleHandlerTest extends TestCase
{
    private const string AUTHORIZATION = 'FR/V/1234567 8/2020';

    public function testAnArchivedNonDrugArticleIsRestoredWithoutAskingTheRegistry(): void
    {
        $article = F::consumable(ArticleStatus::ARCHIVED);

        $refProvider = $this->createMock(PharmaceuticalRefProviderInterface::class);
        $refProvider->expects(self::never())->method('findById');

        $eventBus = $this->createMock(EventBusInterface::class);
        $eventBus->expects(self::once())->method('publish')
            ->with([], self::isInstanceOf(ArticleRestored::class))
        ;

        $handler = $this->handler($this->repositoryExpectingSave($article), $eventBus, $refProvider);
        $handler(new RestoreArticle(F::ARTICLE_ID, F::CLINIC_ID));

        self::assertSame(ArticleStatus::ACTIVE, $article->status());
    }

    public function testADrugWithoutAuthorizationIsRestoredWithoutAskingTheRegistry(): void
    {
        $article = F::drug(ArticleStatus::ARCHIVED, null);

        $refProvider = $this->createMock(PharmaceuticalRefProviderInterface::class);
        $refProvider->expects(self::never())->method('findById');

        $this->handler(
            $this->repositoryExpectingSave($article),
            $this->createStub(EventBusInterface::class),
            $refProvider,
        )(new RestoreArticle(F::ARTICLE_ID, F::CLINIC_ID));

        self::assertSame(ArticleStatus::ACTIVE, $article->status());
    }

    public function testADrugWhoseAuthorizationIsStillMarketableIsRestored(): void
    {
        $article = F::drug(ArticleStatus::ARCHIVED, self::AUTHORIZATION);

        $refProvider = $this->createMock(PharmaceuticalRefProviderInterface::class);
        $refProvider->expects(self::once())->method('findById')->with(self::AUTHORIZATION)
            ->willReturn(F::pharmaceuticalRef(self::AUTHORIZATION, 'ACTIVE'))
        ;

        $this->handler(
            $this->repositoryExpectingSave($article),
            $this->createStub(EventBusInterface::class),
            $refProvider,
        )(new RestoreArticle(F::ARTICLE_ID, F::CLINIC_ID));

        self::assertSame(ArticleStatus::ACTIVE, $article->status());
    }

    public function testADrugWhoseAuthorizationWasWithdrawnStaysArchived(): void
    {
        $this->assertRestoreForbidden(F::pharmaceuticalRef(self::AUTHORIZATION, 'WITHDRAWN'));
    }

    public function testADrugWhoseAuthorizationIsNoLongerInTheRegistryStaysArchived(): void
    {
        $this->assertRestoreForbidden(null);
    }

    public function testAnUnknownArticleIsRefused(): void
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $this->expectException(ArticleNotFoundException::class);

        $this->handler(
            $repository,
            $this->createStub(EventBusInterface::class),
            $this->createStub(PharmaceuticalRefProviderInterface::class),
        )(new RestoreArticle(F::ARTICLE_ID, F::CLINIC_ID));
    }

    private function assertRestoreForbidden(?PharmaceuticalRef $ref): void
    {
        $article = F::drug(ArticleStatus::ARCHIVED, self::AUTHORIZATION);

        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->expects(self::never())->method('save');

        $refProvider = $this->createStub(PharmaceuticalRefProviderInterface::class);
        $refProvider->method('findById')->willReturn($ref);

        try {
            $handler = $this->handler($repository, $this->createStub(EventBusInterface::class), $refProvider);
            $handler(new RestoreArticle(F::ARTICLE_ID, F::CLINIC_ID));
            self::fail('The restore should have been refused.');
        } catch (RegulatoryRestoreForbiddenException) {
            self::assertSame(ArticleStatus::ARCHIVED, $article->status());
        }
    }

    private function repositoryExpectingSave(Article $article): ArticleRepositoryInterface
    {
        $repository = $this->createMock(ArticleRepositoryInterface::class);
        $repository->method('findById')->willReturn($article);
        $repository->expects(self::once())->method('save')->with($article);

        return $repository;
    }

    private function handler(
        ArticleRepositoryInterface $repository,
        EventBusInterface $eventBus,
        PharmaceuticalRefProviderInterface $refProvider,
    ): RestoreArticleHandler {
        return new RestoreArticleHandler(
            $repository,
            new FrozenClock(F::now()),
            new DomainEventPublisher($eventBus),
            $refProvider,
        );
    }
}
