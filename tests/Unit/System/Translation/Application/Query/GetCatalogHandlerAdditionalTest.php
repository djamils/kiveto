<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Translation\Application\Query;

use App\System\Translation\Application\Port\CatalogCacheInterface;
use App\System\Translation\Application\Query\GetCatalog\GetCatalog;
use App\System\Translation\Application\Query\GetCatalog\GetCatalogHandler;
use App\System\Translation\Domain\Repository\TranslationSearchRepository;
use App\System\Translation\Domain\ValueObject\TranslationCatalogId;
use PHPUnit\Framework\TestCase;

final class GetCatalogHandlerAdditionalTest extends TestCase
{
    public function testSharedScopeReturnsDirectCatalog(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::once())
            ->method('findCatalog')
            ->willReturn(['foo' => 'bar'])
        ;

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturn(null);
        $cache->expects(self::once())->method('save')
            ->with(self::isInstanceOf(TranslationCatalogId::class), self::isArray(), 3600)
        ;

        $handler = new GetCatalogHandler($repo, $cache);

        $result = $handler(new GetCatalog('shared', 'fr-FR', 'messages'));

        self::assertSame(['foo' => 'bar'], $result);
    }

    public function testUsesCachedCatalog(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::never())->method('findCatalog');

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::exactly(2))
            ->method('get')
            ->willReturn(['cached' => 'yes'])
        ;
        $cache->expects(self::never())->method('save');

        $handler = new GetCatalogHandler($repo, $cache);

        $result = $handler(new GetCatalog('clinic', 'fr-FR', 'messages'));

        self::assertSame(['cached' => 'yes'], $result);
    }
}
