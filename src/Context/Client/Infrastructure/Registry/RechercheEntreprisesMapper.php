<?php

declare(strict_types=1);

namespace App\Context\Client\Infrastructure\Registry;

use App\Context\Client\Application\Port\CompanyRecord;
use App\Context\Client\Application\Port\CompanySummary;

/**
 * Reads the answers of the API Recherche d'entreprises
 * (https://recherche-entreprises.api.gouv.fr/docs/). Tolerant on purpose: a
 * result without a well-formed SIREN is skipped, any other missing field is null.
 */
final readonly class RechercheEntreprisesMapper
{
    private const string MASKED_NAME = 'Entreprise non diffusible';

    /**
     * @param array<mixed> $data
     *
     * @return list<array<mixed>> the results carrying a 9-digit SIREN
     */
    public function results(array $data): array
    {
        $results = [];

        foreach (\is_array($data['results'] ?? null) ? $data['results'] : [] as $result) {
            if (\is_array($result) && \is_string($result['siren'] ?? null) && 1 === preg_match('/^\d{9}$/', $result['siren'])) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param array<mixed> $result
     */
    public function summary(array $result): CompanySummary
    {
        $siege       = self::map($result, 'siege');
        $complements = self::map($result, 'complements');
        $postalCode  = self::string($siege, 'code_postal');
        $city        = self::string($siege, 'libelle_commune');

        return new CompanySummary(
            siren: (string) self::string($result, 'siren'),
            name: $this->name($result) ?? self::MASKED_NAME,
            legalFormLabel: LegalForms::label(self::string($result, 'nature_juridique')),
            nafCode: self::string($result, 'activite_principale'),
            isAssociation: true === ($complements['est_association'] ?? null),
            isActive: $this->isActive($result),
            ceasedOn: $this->ceasedOn($result),
            headOfficeSiret: self::string($siege, 'siret'),
            addressLine: $this->addressLine(self::string($siege, 'adresse'), $postalCode, $city),
            postalCode: $postalCode,
            city: $city,
        );
    }

    /**
     * @param array<mixed> $result
     */
    public function record(array $result): CompanyRecord
    {
        $name = $this->name($result);

        return new CompanyRecord(
            siren: (string) self::string($result, 'siren'),
            legalName: $name,
            isActive: $this->isActive($result),
            isConfidential: null === $name,
            ceasedOn: $this->ceasedOn($result),
        );
    }

    /**
     * @param array<mixed> $result
     */
    public function headOfficeSiret(array $result): ?string
    {
        return self::string(self::map($result, 'siege'), 'siret');
    }

    /**
     * The legal name, null when the company is non-diffusible (masked).
     *
     * @param array<mixed> $result
     */
    private function name(array $result): ?string
    {
        $diffusion = self::string($result, 'statut_diffusion');
        $name      = self::string($result, 'nom_raison_sociale') ?? self::string($result, 'nom_complet');

        if (null === $name || \in_array($diffusion, ['P', 'N'], true) || str_contains($name, 'NON-DIFFUSIBLE')) {
            return null;
        }

        return $name;
    }

    /**
     * @param array<mixed> $result
     */
    private function isActive(array $result): bool
    {
        return 'C' !== self::string($result, 'etat_administratif');
    }

    /**
     * @param array<mixed> $result
     */
    private function ceasedOn(array $result): ?string
    {
        $date = self::string($result, 'date_fermeture');

        return null !== $date && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    /**
     * « 12 CHEMIN DES GOUDES 13008 MARSEILLE » without its trailing postal code and city.
     */
    private function addressLine(?string $address, ?string $postalCode, ?string $city): ?string
    {
        if (null === $address) {
            return null;
        }

        $suffix = trim(\sprintf('%s %s', $postalCode, $city));
        $line   = '' !== $suffix && str_ends_with($address, $suffix) ? substr($address, 0, -\strlen($suffix)) : $address;
        $line   = trim($line, " ,\t");

        return '' === $line ? null : $line;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function map(array $data, string $key): array
    {
        return \is_array($data[$key] ?? null) ? $data[$key] : [];
    }

    /**
     * @param array<mixed> $data
     */
    private static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (\is_int($value)) {
            $value = (string) $value;
        }

        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return trim($value);
    }
}
