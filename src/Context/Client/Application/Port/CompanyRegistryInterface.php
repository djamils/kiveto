<?php

declare(strict_types=1);

namespace App\Context\Client\Application\Port;

use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;

/**
 * The national company registry (in France the « Annuaire des entreprises »,
 * fed by SIRENE), consulted to pick a company and to verify a SIREN.
 *
 * An outage is never an empty result: when the registry cannot answer, an
 * ExternalServiceUnavailable is thrown — "throttled" (our budget is spent or
 * the registry asks us to slow down: try again in a moment) or "down".
 */
interface CompanyRegistryInterface
{
    /**
     * Companies matching a name, a SIREN (9 digits) or a head-office SIRET
     * (14 digits), active ones first. Empty only when the registry found nothing.
     *
     * @throws ExternalServiceUnavailable
     *
     * @return list<CompanySummary>
     */
    public function search(string $query, int $limit): array;

    /**
     * The company holding this SIREN, null when the registry does not know it.
     *
     * @throws ExternalServiceUnavailable
     */
    public function findBySiren(string $siren): ?CompanyRecord;
}
