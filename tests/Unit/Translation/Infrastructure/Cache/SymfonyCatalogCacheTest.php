<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Cache;

use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationDomain;
use App\Translation\Infrastructure\Cache\SymfonyCatalogCache;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class SymfonyCatalogCacheTest extends TestCase
{
    public function testSaveGetDeleteWithSanitizedKey(): void
    {
        $pool      = new ArrayAdapter();
        $cache     = new SymfonyCatalogCache($pool);
        $catalogId = new TranslationCatalogId(
            AppScope::CLINIC,
            Locale::fromString('fr_FR'),
            TranslationDomain::fromString('messages'),
        );

        $cache->save($catalogId, ['foo' => 'bar'], 3600);
        $fromCache = $cache->get($catalogId);

        self::assertSame(['foo' => 'bar'], $fromCache);

        $cache->delete($catalogId);
        self::assertNull($cache->get($catalogId));
    }

    public function testGetReturnsNullWhenNotArray(): void
    {
        $pool  = new ArrayAdapter();
        $cache = new SymfonyCatalogCache($pool);
        $id    = new TranslationCatalogId(
            AppScope::CLINIC,
            Locale::fromString('fr_FR'),
            TranslationDomain::fromString('common'),
        );

        $item = $pool->getItem('translation.catalog.v1.clinic.fr_FR.common');
        $item->set('not-an-array');
        $pool->save($item);

        self::assertNull($cache->get($id));
    }
}
