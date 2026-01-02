<?php

declare(strict_types=1);

namespace App\Translation\Domain\Repository;

use App\Translation\Domain\Model\ValueObject\AppScope;
use App\Translation\Domain\Model\ValueObject\Locale;
use App\Translation\Domain\Model\ValueObject\TranslationDomain;

/**
 * Read-side optimized repository for translations.
 */
interface TranslationSearchRepository
{
    /**
     * @return array<string, string>
     */
    public function findCatalog(AppScope $scope, Locale $locale, TranslationDomain $domain): array;

    /**
     * @return array{
     *     items: list<array<string, mixed>>,
     *     total: int
     * }
     */
    public function search(array $criteria, int $page, int $perPage): array;

    /**
     * @return list<string>
     */
    public function listDomains(?AppScope $scope, ?Locale $locale): array;

    /**
     * @return list<string>
     */
    public function listLocales(?AppScope $scope, ?TranslationDomain $domain): array;
}
