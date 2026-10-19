<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Client;

use App\Context\Client\Application\Port\CompanySummary;
use App\Context\Client\Application\Query\SearchCompanyRegistry\CompanySearchResult;
use App\Shared\Domain\ValueObject\FrenchVatNumber;
use App\Shared\Domain\ValueObject\Siren;

/**
 * A registry hit as the tax-profile screens read it (`CompanyHit`):
 * {siren, n, forme, naf, ass, cesse, ets: [{siret, l, a}], tva}. `ass` proposes
 * the company as VAT-liable (an association generally is not); `cesse` is
 * the closing date, `true` when the registry gives none, null when active;
 * `tva` is the VAT number the SIREN would carry, to be confirmed by the user.
 */
final readonly class CompanyHitPresenter
{
    /**
     * @return array{available: bool, reason: string|null, items: list<array<string, mixed>>}
     */
    public function result(CompanySearchResult $result): array
    {
        return [
            'available' => $result->available,
            'reason'    => $result->reason,
            'items'     => array_map($this->hit(...), $result->hits),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function hit(CompanySummary $company): array
    {
        $place = trim(\sprintf('%s %s', $company->postalCode, $company->city));

        return [
            'siren' => $company->siren,
            'n'     => $company->name,
            'forme' => $company->legalFormLabel ?? '',
            'naf'   => $company->nafCode,
            'ass'   => !$company->isAssociation,
            'cesse' => $company->ceasedOn ?? ($company->isActive ? null : true),
            'ets'   => [[
                'siret' => $company->headOfficeSiret ?? '',
                'l'     => 'Siège',
                'a'     => implode(', ', array_filter([$company->addressLine, '' === $place ? null : $place])),
            ]],
            'tva' => $this->vatNumber($company->siren),
        ];
    }

    private function vatNumber(string $siren): ?string
    {
        try {
            return FrenchVatNumber::forSiren(Siren::fromInput($siren))->value();
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
