<?php

declare(strict_types=1);

namespace App\Translation\Application\Query\SearchTranslations;

use App\Translation\Domain\Model\ValueObject\AppScope;
use App\Translation\Domain\Model\ValueObject\Locale;
use App\Translation\Domain\Model\ValueObject\TranslationDomain;
use App\Translation\Domain\Repository\TranslationSearchRepository;
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
                $row['scope'],
                $row['locale'],
                $row['domain'],
                $row['key'],
                $row['value'],
                $row['updatedAt'],
                $row['updatedBy'],
            ),
            $result['items'],
        );

        return new TranslationSearchResult($items, $result['total'], $page, $perPage);
    }
}
