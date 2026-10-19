<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Registry;

use App\Context\Client\Application\Port\CompanyRecord;
use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Keeps each container under its share of the registry's rate limit: a user
 * search spends the web budget and is refused ("throttled") when it is
 * empty; a verification (worker) waits for the worker budget instead.
 */
final readonly class ThrottledCompanyRegistry implements CompanyRegistryInterface
{
    // One bucket per container, whatever the caller.
    private const string BUCKET = 'global';

    public function __construct(
        private CompanyRegistryInterface $inner,
        private RateLimiterFactoryInterface $webLimiter,
        private RateLimiterFactoryInterface $workerLimiter,
    ) {
    }

    public function search(string $query, int $limit): array
    {
        if (!$this->webLimiter->create(self::BUCKET)->consume()->isAccepted()) {
            throw ExternalServiceUnavailable::throttled(RechercheEntreprisesCompanyRegistry::SERVICE);
        }

        return $this->inner->search($query, $limit);
    }

    public function findBySiren(string $siren): ?CompanyRecord
    {
        $this->workerLimiter->create(self::BUCKET)->reserve()->wait();

        return $this->inner->findBySiren($siren);
    }
}
