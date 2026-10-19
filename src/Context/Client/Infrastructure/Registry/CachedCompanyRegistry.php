<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Registry;

use App\Context\Client\Application\Port\CompanyRecord;
use App\Context\Client\Application\Port\CompanyRegistryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Keeps the search answers an hour (people retype the same name). A SIREN
 * lookup is a verification: it always reads fresh data. An outage throws
 * out of the callback, so it is never cached.
 */
final readonly class CachedCompanyRegistry implements CompanyRegistryInterface
{
    private const int SEARCH_TTL = 3600;

    public function __construct(
        private CompanyRegistryInterface $inner,
        private CacheInterface $cache,
    ) {
    }

    public function search(string $query, int $limit): array
    {
        $key = \sprintf('client.company_registry.search.%s.%d', hash('xxh128', mb_strtolower(trim($query))), $limit);

        return $this->cache->get($key, function (ItemInterface $item) use ($query, $limit): array {
            $item->expiresAfter(self::SEARCH_TTL);

            return $this->inner->search($query, $limit);
        });
    }

    public function findBySiren(string $siren): ?CompanyRecord
    {
        return $this->inner->findBySiren($siren);
    }
}
