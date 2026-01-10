<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Application\Query;

use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Application\Query\GetCatalog\GetCatalog;
use App\Translation\Application\Query\GetCatalog\GetCatalogHandler;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use PHPUnit\Framework\TestCase;

final class GetCatalogHandlerTest extends TestCase
{
    public function testReturnsMergedWithFallbackAndCaches(): void
    {
        $repo = $this->createMock(TranslationSearchRepository::class);
        $repo->expects(self::exactly(2))
            ->method('findCatalog')
            ->willReturnOnConsecutiveCalls(
                ['a' => '1'],
                ['b' => '2'],
            )
        ;

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(null, null)
        ;
        $cache->expects(self::exactly(2))
            ->method('save')
            ->with(self::isInstanceOf(TranslationCatalogId::class), self::isArray(), 3600)
        ;

        $handler = new GetCatalogHandler($repo, $cache);

        $result = $handler(new GetCatalog('clinic', 'fr-FR', 'messages'));

        self::assertSame(['a' => '1', 'b' => '2'], $result);
    }
}
