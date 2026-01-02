<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\GetCatalog;

use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetCatalogHandler
{
    private const int DEFAULT_TTL = 3600;

    public function __construct(
        private TranslationSearchRepository $repository,
        private CatalogCacheInterface $cache,
        private int $catalogTtl = self::DEFAULT_TTL,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function __invoke(GetCatalog $query): array
    {
        $scopeId = TranslationCatalogId::fromStrings($query->scope, $query->locale, $query->domain);

        $scopeCatalog = $this->loadCatalog($scopeId);

        if (AppScope::SHARED === $scopeId->scope()) {
            return $scopeCatalog;
        }

        $sharedId      = new TranslationCatalogId(AppScope::SHARED, $scopeId->locale(), $scopeId->domain());
        $sharedCatalog = $this->loadCatalog($sharedId);

        return $this->mergeWithFallback($scopeCatalog, $sharedCatalog);
    }

    /**
     * @return array<string, string>
     */
    private function loadCatalog(TranslationCatalogId $id): array
    {
        $cached = $this->cache->get($id);

        if (null !== $cached) {
            return $cached;
        }

        $catalog = $this->repository->findCatalog($id->scope(), $id->locale(), $id->domain());
        $this->cache->save($id, $catalog, $this->catalogTtl);

        return $catalog;
    }

    /**
     * @param array<string, string> $scopeCatalog
     * @param array<string, string> $sharedCatalog
     *
     * @return array<string, string>
     */
    private function mergeWithFallback(array $scopeCatalog, array $sharedCatalog): array
    {
        return $scopeCatalog + $sharedCatalog;
    }
}
