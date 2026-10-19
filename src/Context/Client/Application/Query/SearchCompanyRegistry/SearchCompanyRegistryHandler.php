<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Query\SearchCompanyRegistry;

use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchCompanyRegistryHandler
{
    public function __construct(
        private CompanyRegistryInterface $registry,
    ) {
    }

    public function __invoke(SearchCompanyRegistry $query): CompanySearchResult
    {
        $text   = trim((string) preg_replace('/\s+/u', ' ', $query->query));
        $digits = str_replace([' ', '.', '-'], '', $text);

        // A SIREN or a SIRET is searched as such; fewer than 3 letters would match half the registry.
        if (1 !== preg_match('/^(\d{9}|\d{14})$/', $digits) && mb_strlen($text) < SearchCompanyRegistry::MIN_LENGTH) {
            return new CompanySearchResult(true, null, []);
        }

        try {
            $hits = $this->registry->search($text, max(1, min(SearchCompanyRegistry::MAX_LIMIT, $query->limit)));
        } catch (ExternalServiceUnavailable $e) {
            // An outage is not "nothing found": the user is told and can type the company in.
            return new CompanySearchResult(false, $e->isThrottled() ? CompanySearchResult::THROTTLED : CompanySearchResult::DOWN, []);
        }

        return new CompanySearchResult(true, null, $hits);
    }
}
