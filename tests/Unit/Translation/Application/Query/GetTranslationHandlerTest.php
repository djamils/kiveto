<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Application\Query;

use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Application\Query\GetTranslation\GetTranslation;
use App\Translation\Application\Query\GetTranslation\GetTranslationHandler;
use App\Translation\Application\Query\GetTranslation\TranslationView;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use PHPUnit\Framework\TestCase;

final class GetTranslationHandlerTest extends TestCase
{
    public function testReturnsValueWithFallbackAndCaches(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::exactly(2))
            ->method('findCatalog')
            ->willReturnOnConsecutiveCalls(
                ['foo' => '1'],
                ['bar' => '2'],
            )
        ;

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::exactly(2))
            ->method('get')
            ->willReturn(null)
        ;
        $cache->expects(self::exactly(2))
            ->method('save')
            ->with(self::isInstanceOf(TranslationCatalogId::class), self::isArray(), 3600)
        ;

        $handler = new GetTranslationHandler($repo, $cache);

        /** @var TranslationView $view */
        $view = $handler(new GetTranslation('clinic', 'fr-FR', 'messages', 'bar'));

        self::assertSame('shared', $view->scope);
        self::assertSame('fr-FR', $view->locale);
        self::assertSame('messages', $view->domain);
        self::assertSame('bar', $view->key);
        self::assertSame('2', $view->value);
    }

    public function testReturnsDirectHitFromScope(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::once())
            ->method('findCatalog')
            ->willReturn(['foo' => 'bar'])
        ;

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->willReturn(null)
        ;
        $cache->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(TranslationCatalogId::class), self::isArray(), 3600)
        ;

        $handler = new GetTranslationHandler($repo, $cache);

        $view = $handler(new GetTranslation('clinic', 'fr-FR', 'messages', 'foo'));

        self::assertInstanceOf(TranslationView::class, $view);
        self::assertSame('clinic', $view->scope);
        self::assertSame('bar', $view->value);
    }

    public function testReturnsNullWhenNotFound(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::once())
            ->method('findCatalog')
            ->willReturn([])
        ;

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->willReturn(null)
        ;
        $cache->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(TranslationCatalogId::class), self::isArray(), 3600)
        ;

        $handler = new GetTranslationHandler($repo, $cache);

        $view = $handler(new GetTranslation('shared', 'fr-FR', 'messages', 'missing'));

        self::assertNull($view);
    }

    public function testReturnsNullWhenFallbackMissing(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::exactly(2))
            ->method('findCatalog')
            ->willReturn([], [])
        ;

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::exactly(2))
            ->method('get')
            ->willReturn(null)
        ;
        $cache->expects(self::exactly(2))
            ->method('save')
            ->with(self::isInstanceOf(TranslationCatalogId::class), self::isArray(), 3600)
        ;

        $handler = new GetTranslationHandler($repo, $cache);

        $view = $handler(new GetTranslation('clinic', 'fr-FR', 'messages', 'missing'));

        self::assertNull($view);
    }

    public function testUsesCachedCatalog(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::never())->method('findCatalog');

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->willReturn(['cached' => 'ok'])
        ;
        $cache->expects(self::never())->method('save');

        $handler = new GetTranslationHandler($repo, $cache);

        $view = $handler(new GetTranslation('clinic', 'fr-FR', 'messages', 'cached'));

        self::assertNotNull($view);
        self::assertSame('ok', $view->value);
        self::assertSame('clinic', $view->scope);
    }
}
