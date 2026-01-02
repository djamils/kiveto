<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Cache;

use App\Translation\Application\Port\CatalogCache;
use App\Translation\Domain\Model\ValueObject\TranslationCatalogId;
use Psr\Cache\CacheItemPoolInterface;

final readonly class SymfonyCatalogCache implements CatalogCache
{
    private const string PREFIX = 'translation:catalog:v1:';

    public function __construct(private CacheItemPoolInterface $cachePool)
    {
    }

    public function get(TranslationCatalogId $id): ?array
    {
        $item = $this->cachePool->getItem($this->key($id));

        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        return \is_array($value) ? $value : null;
    }

    public function save(TranslationCatalogId $id, array $catalog, int $ttl): void
    {
        $item = $this->cachePool->getItem($this->key($id));
        $item->set($catalog);
        $item->expiresAfter($ttl);
        $this->cachePool->save($item);
    }

    public function delete(TranslationCatalogId $id): void
    {
        $this->cachePool->deleteItem($this->key($id));
    }

    private function key(TranslationCatalogId $id): string
    {
        return self::PREFIX . $id->cacheKeyPart();
    }
}
