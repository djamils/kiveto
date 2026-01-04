<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Application\Command;

use App\Shared\Domain\Time\ClockInterface;
use App\Translation\Application\Command\BulkUpsertTranslations\BulkUpsertTranslations;
use App\Translation\Application\Command\BulkUpsertTranslations\BulkUpsertTranslationsHandler;
use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationCatalogRepository;
use App\Translation\Domain\TranslationCatalog;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use PHPUnit\Framework\TestCase;

final class BulkUpsertTranslationsHandlerTest extends TestCase
{
    public function testBulkGroupsByCatalogInvalidatesAndPublishes(): void
    {
        $repo = $this->createMock(TranslationCatalogRepository::class);
        $repo->expects(self::exactly(2))
            ->method('find')
            ->willReturn(null)
        ;
        $repo->expects(self::exactly(2))
            ->method('save')
            ->with(self::isInstanceOf(TranslationCatalog::class))
        ;

        $cache = $this->createMock(CatalogCacheInterface::class);
        $cache->expects(self::exactly(2))
            ->method('delete')
            ->with(self::isInstanceOf(TranslationCatalogId::class))
        ;

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2024-01-01T12:00:00Z'));

        $handler = new BulkUpsertTranslationsHandler($repo, $cache, $clock);

        $handler(new BulkUpsertTranslations([
            ['scope' => 'clinic', 'locale' => 'fr_FR', 'domain' => 'messages', 'key' => 'k1', 'value' => 'v1'],
            ['scope' => 'clinic', 'locale' => 'fr_FR', 'domain' => 'messages', 'key' => 'k2', 'value' => 'v2'],
            ['scope' => 'portal', 'locale' => 'en_GB', 'domain' => 'auth', 'key' => 'k3', 'value' => 'v3'],
        ]));
    }
}
