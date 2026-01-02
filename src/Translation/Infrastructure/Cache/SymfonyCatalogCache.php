<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Cache;

use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use Psr\Cache\CacheItemPoolInterface;

final readonly class SymfonyCatalogCache implements CatalogCacheInterface
{
    private const string PREFIX = 'translation:catalog:v1:';

    public function __construct(private CacheItemPoolInterface $cachePool)
    {
    }

    /**
     * @return array<string, string>|null
     */
    public function get(TranslationCatalogId $id): ?array
    {
        $item = $this->cachePool->getItem($this->key($id));

        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        if (!\is_array($value)) {
            return null;
        }

        $catalog = [];

        foreach ($value as $key => $val) {
            if (\is_string($key) && \is_string($val)) {
                $catalog[$key] = $val;
            }
        }

        return $catalog;
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
