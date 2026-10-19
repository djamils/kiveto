<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Registry;

use App\Context\Client\Application\Port\CompanyRecord;
use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Application\Port\CompanySummary;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Infrastructure\ExternalApi\ExternalApiResponse;
use App\Shared\Infrastructure\ExternalApi\ExternalApiUserAgent;
use App\Shared\Infrastructure\ExternalApi\RetryAfterAwareClient;

/**
 * The API Recherche d'entreprises (free, no key, about 7 requests per second
 * per IP), through the `registry.client` scoped client.
 */
final readonly class RechercheEntreprisesCompanyRegistry implements CompanyRegistryInterface
{
    public const string SERVICE = 'recherche-entreprises';

    private const int MAX_PER_PAGE = 25;

    public function __construct(
        private RetryAfterAwareClient $client,
        private RechercheEntreprisesMapper $mapper,
        ExternalApiUserAgent $userAgent,
    ) {
        // Built only on demand (CLIENT_COMPANY_REGISTRY=api): fails here without a contact.
        $userAgent->withContact(self::SERVICE);
    }

    public function search(string $query, int $limit): array
    {
        $query  = trim($query);
        $digits = str_replace([' ', '.', '-'], '', $query);
        $siret  = 1 === preg_match('/^\d{14}$/', $digits) ? $digits : null;

        $results = $this->results(null === $siret ? $query : $siret, min(max($limit, 1), self::MAX_PER_PAGE));

        if (null !== $siret) {
            $results = array_values(array_filter($results, fn (array $result): bool => $this->mapper->headOfficeSiret($result) === $siret));
        }

        $hits = array_map($this->mapper->summary(...), $results);
        usort($hits, static fn (CompanySummary $a, CompanySummary $b): int => $b->isActive <=> $a->isActive);

        return \array_slice($hits, 0, max(0, $limit));
    }

    public function findBySiren(string $siren): ?CompanyRecord
    {
        foreach ($this->results($siren, 5) as $result) {
            if ($siren === $result['siren']) {
                return $this->mapper->record($result);
            }
        }

        return null;
    }

    /**
     * @throws ExternalServiceUnavailable
     *
     * @return list<array<mixed>>
     */
    private function results(string $query, int $perPage): array
    {
        $response = $this->client->request('GET', '/search', ['query' => [
            'q'        => $query,
            'per_page' => $perPage,
            'page'     => 1,
            'minimal'  => 'true',
            'include'  => 'siege,complements',
        ]]);

        // A 400 is the API refusing the query itself (too short, malformed): nothing to find.
        if (400 === $response->status) {
            return [];
        }

        return $this->mapper->results($this->data($response));
    }

    /**
     * @return array<mixed>
     */
    private function data(ExternalApiResponse $response): array
    {
        if (!$response->isSuccessful()) {
            throw ExternalServiceUnavailable::refused(self::SERVICE, \sprintf('HTTP %d', $response->status));
        }

        if (null === $response->data) {
            throw ExternalServiceUnavailable::refused(self::SERVICE, 'unreadable answer');
        }

        return $response->data;
    }
}
