<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\GetTranslation;

use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetTranslationHandler
{
    private const int DEFAULT_TTL = 3600;

    public function __construct(
        private TranslationSearchRepository $repository,
        private CatalogCacheInterface $cache,
        private int $catalogTtl = self::DEFAULT_TTL,
    ) {
    }

    public function __invoke(GetTranslation $query): ?TranslationView
    {
        $catalogId    = TranslationCatalogId::fromStrings($query->scope, $query->locale, $query->domain);
        $scopeCatalog = $this->loadCatalog($catalogId);

        $value = $scopeCatalog[$query->key] ?? null;

        if (null !== $value) {
            return new TranslationView($query->scope, $query->locale, $query->domain, $query->key, $value);
        }

        if (AppScope::SHARED === $catalogId->scope()) {
            return null;
        }

        $sharedId      = new TranslationCatalogId(AppScope::SHARED, $catalogId->locale(), $catalogId->domain());
        $sharedCatalog = $this->loadCatalog($sharedId);
        $fallbackValue = $sharedCatalog[$query->key] ?? null;

        if (null === $fallbackValue) {
            return null;
        }

        return new TranslationView(
            $sharedId->scope()->value,
            $sharedId->locale()->toString(),
            $sharedId->domain()->toString(),
            $query->key,
            $fallbackValue
        );
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
}
