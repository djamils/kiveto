<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Provider;

use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use Symfony\Contracts\Service\ResetInterface;

class TranslationCatalogProvider implements ResetInterface
{
    /** @var array<string, array<string, string>> */
    private array $effectiveCatalogs = [];

    /** @var array<string, array<string, string>> */
    private array $rawCatalogs = [];

    public function __construct(
        private readonly TranslationSearchRepository $repository,
        private readonly CatalogCacheInterface $cache,
        private readonly int $ttl = 3600,
    ) {
    }

    /**
     * Clears in-memory memoization to avoid cross-request state leaks in long-running runtimes
     * (e.g. Messenger workers, RoadRunner, Swoole).
     */
    public function reset(): void
    {
        // Keep per-request caches strictly request-scoped.
        $this->effectiveCatalogs = [];
        $this->rawCatalogs       = [];
    }

    /**
     * @return array<string, string>
     */
    public function getEffectiveCatalog(AppScope $scope, Locale $locale, string $domain): array
    {
        $key = $scope->value . '|' . $locale->toString() . '|' . $domain;

        if (isset($this->effectiveCatalogs[$key])) {
            return $this->effectiveCatalogs[$key];
        }

        $scopeId      = TranslationCatalogId::fromStrings($scope->value, $locale->toString(), $domain);
        $scopeCatalog = $this->loadRawCatalog($scopeId);

        if (AppScope::SHARED === $scope) {
            return $this->effectiveCatalogs[$key] = $scopeCatalog;
        }

        $sharedId      = TranslationCatalogId::fromStrings(AppScope::SHARED->value, $locale->toString(), $domain);
        $sharedCatalog = $this->loadRawCatalog($sharedId);

        return $this->effectiveCatalogs[$key] = ($scopeCatalog + $sharedCatalog);
    }

    /**
     * @return array<string, string>
     */
    private function loadRawCatalog(TranslationCatalogId $id): array
    {
        $key = $id->scope()->value . '|' . $id->locale()->toString() . '|' . $id->domain()->toString();

        if (isset($this->rawCatalogs[$key])) {
            return $this->rawCatalogs[$key];
        }

        $cached = $this->cache->get($id);
        if (null !== $cached) {
            return $this->rawCatalogs[$key] = $cached;
        }

        $catalog = $this->repository->findCatalog($id->scope(), $id->locale(), $id->domain());
        $this->cache->save($id, $catalog, $this->ttl);

        return $this->rawCatalogs[$key] = $catalog;
    }
}
