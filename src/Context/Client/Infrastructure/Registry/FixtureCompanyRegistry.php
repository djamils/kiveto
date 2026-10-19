<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Registry;

use App\Context\Client\Application\Port\CompanyRecord;
use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Application\Port\CompanySummary;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;

/**
 * In-memory registry for tests, CI and dev (CLIENT_COMPANY_REGISTRY=fixture),
 * aligned on the demo clients. Any other valid SIREN is unknown.
 *
 * Triggers: the query « indisponible » or UNAVAILABLE_SIREN act as an outage,
 * the query « limite » as a spent budget.
 */
final class FixtureCompanyRegistry implements CompanyRegistryInterface
{
    public const string SERVICE = 'recherche-entreprises';

    public const string UNAVAILABLE_QUERY = 'indisponible';
    public const string THROTTLED_QUERY   = 'limite';
    // Luhn-valid, so that it can be typed as a SIREN.
    public const string UNAVAILABLE_SIREN  = '999999998';
    public const string CONFIDENTIAL_SIREN = '900000019';

    /**
     * siren (an int key once PHP casts it) => [name, legal form code, NAF, association, ceased on, head-office SIRET, address, postal code, city].
     *
     * @var array<int|string, array{string|null, string, string, bool, string|null, string, string, string, string}>
     */
    private const array COMPANIES = [
        '523456788'              => ['Élevage des Calanques', '6598', '01.45Z', false, null, '52345678800018', '12 Chemin des Goudes', '13008', 'Marseille'],
        '812345676'              => ['Centre équestre du Lac', '5710', '93.19Z', false, null, '81234567600017', 'Route du Lac', '95880', 'Enghien-les-Bains'],
        '441569738'              => ['GAEC du Mont', '6533', '01.41Z', false, '2025-12-31', '44156973800012', 'Lieu-dit Le Mont', '19000', 'Tulle'],
        '392107454'              => ['Haras du Mont Ventoux', '6597', '01.43Z', false, null, '39210745400013', 'Chemin du Ventoux', '84410', 'Bédoin'],
        '775612344'              => ['Refuge des Collines', '9220', '94.99Z', true, null, '77561234400017', '12 rue des Tilleuls', '93100', 'Montreuil'],
        self::CONFIDENTIAL_SIREN => [null, '1000', '01.49Z', false, null, '90000001900011', '', '', ''],
    ];

    public function search(string $query, int $limit): array
    {
        $query = trim($query);
        $this->failOnTrigger(mb_strtolower($query));

        $digits = str_replace([' ', '.', '-'], '', $query);
        $hits   = [];

        foreach (self::COMPANIES as $siren => $company) {
            if ($this->matches((string) $siren, $company, $query, $digits)) {
                $hits[] = $this->summary((string) $siren, $company);
            }
        }

        usort($hits, static fn (CompanySummary $a, CompanySummary $b): int => $b->isActive <=> $a->isActive);

        return \array_slice($hits, 0, max(0, $limit));
    }

    public function findBySiren(string $siren): ?CompanyRecord
    {
        $this->failOnTrigger($siren);

        $company = self::COMPANIES[$siren] ?? null;

        if (null === $company) {
            return null;
        }

        return new CompanyRecord(
            siren: $siren,
            legalName: $company[0],
            isActive: null === $company[4],
            isConfidential: null === $company[0],
            ceasedOn: $company[4],
        );
    }

    private function failOnTrigger(string $value): void
    {
        if (self::UNAVAILABLE_QUERY === $value || self::UNAVAILABLE_SIREN === $value) {
            throw ExternalServiceUnavailable::failed(self::SERVICE, 503);
        }

        if (self::THROTTLED_QUERY === $value) {
            throw ExternalServiceUnavailable::throttled(self::SERVICE);
        }
    }

    /**
     * @param array{string|null, string, string, bool, string|null, string, string, string, string} $company
     */
    private function matches(string $siren, array $company, string $query, string $digits): bool
    {
        if (1 === preg_match('/^\d{9}$/', $digits)) {
            return $siren === $digits;
        }

        if (1 === preg_match('/^\d{14}$/', $digits)) {
            return $company[5] === $digits;
        }

        // A masked company is only found by its identifiers.
        return null !== $company[0] && '' !== $query && str_contains(self::fold($company[0]), self::fold($query));
    }

    /**
     * @param array{string|null, string, string, bool, string|null, string, string, string, string} $company
     */
    private function summary(string $siren, array $company): CompanySummary
    {
        return new CompanySummary(
            siren: $siren,
            name: $company[0] ?? 'Entreprise non diffusible',
            legalFormLabel: LegalForms::label($company[1]),
            nafCode: $company[2],
            isAssociation: $company[3],
            isActive: null === $company[4],
            ceasedOn: $company[4],
            headOfficeSiret: $company[5],
            addressLine: '' === $company[6] ? null : $company[6],
            postalCode: '' === $company[7] ? null : $company[7],
            city: '' === $company[8] ? null : $company[8],
        );
    }

    private static function fold(string $text): string
    {
        $decomposed = \Normalizer::normalize(mb_strtolower($text), \Normalizer::FORM_D);

        return \is_string($decomposed) ? (string) preg_replace('/\p{Mn}+/u', '', $decomposed) : $text;
    }
}
