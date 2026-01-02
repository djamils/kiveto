<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\SearchTranslations;

use App\Translation\Domain\Repository\TranslationSearchRepository;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use App\Translation\Domain\ValueObject\TranslationDomain;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SearchTranslationsHandler
{
    public function __construct(private TranslationSearchRepository $repository)
    {
    }

    public function __invoke(SearchTranslations $query): TranslationSearchResult
    {
        $criteria = [
            'scope'         => null !== $query->scope ? AppScope::fromString($query->scope) : null,
            'locale'        => null !== $query->locale ? Locale::fromString($query->locale) : null,
            'domain'        => null !== $query->domain ? TranslationDomain::fromString($query->domain) : null,
            'keyContains'   => $query->keyContains,
            'valueContains' => $query->valueContains,
            'updatedBy'     => $query->updatedBy,
            'updatedAfter'  => $query->updatedAfter,
        ];

        $page    = max(1, $query->page);
        $perPage = max(1, min(200, $query->perPage));

        $result = $this->repository->search($criteria, $page, $perPage);

        $items = array_map(
            static fn (array $row): TranslationSearchItem => new TranslationSearchItem(
                (string) $row['scope'],
                (string) $row['locale'],
                (string) $row['domain'],
                (string) $row['key'],
                (string) $row['value'],
                $row['updatedAt'],
                $row['updatedBy'],
            ),
            $result['items'],
        );

        return new TranslationSearchResult($items, $result['total'], $page, $perPage);
    }
}
