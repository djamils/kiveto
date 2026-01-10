<?php

declare(strict_types=1);

namespace App\Translation\Domain\Repository;

use App\Shared\Domain\Localization\Locale;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\TranslationDomain;

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
     * @param array{
     *     scope?: AppScope|null,
     *     locale?: Locale|null,
     *     domain?: TranslationDomain|null,
     *     keyContains?: string|null,
     *     valueContains?: string|null,
     *     updatedBy?: string|null,
     *     updatedAfter?: \DateTimeImmutable|null,
     *     createdBy?: string|null,
     *     createdAfter?: \DateTimeImmutable|null
     * } $criteria
     *
     * @return array{
     *     items: list<array{
     *         scope: string,
     *         locale: string,
     *         domain: string,
     *         key: string,
     *         value: string,
     *         description: string|null,
     *         createdAt: \DateTimeImmutable,
     *         createdBy: string|null,
     *         updatedAt: \DateTimeImmutable,
     *         updatedBy: string|null
     *     }>,
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
