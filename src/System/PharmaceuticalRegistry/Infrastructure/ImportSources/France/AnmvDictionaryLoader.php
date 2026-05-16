<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

final class AnmvDictionaryLoader
{
    private const TYPE_ACTIVE_SUBSTANCE     = 'sub_act';
    private const TYPE_PRESCRIPTION         = 'cond_pres';
    private const TYPE_TARGET_SPECIES       = 'esp';
    private const TYPE_ADMINISTRATION_ROUTE = 'voie_adm';
    private const TYPE_WITHDRAWAL_UNIT      = 'unit_att';

    public function load(string $filePath): AnmvDictionaryCache
    {
        $activeSubstances       = [];
        $prescriptionConditions = [];
        $targetSpecies          = [];
        $administrationRoutes   = [];
        $withdrawalUnits        = [];

        $xml = new \SimpleXMLElement(file_get_contents($filePath) ?: '');

        foreach ($xml->children() as $termGroup) {
            $type = (string) ($termGroup['type'] ?? '');

            foreach ($termGroup->children() as $term) {
                $id    = (int) ($term['id'] ?? 0);
                $label = (string) ($term['label'] ?? '');

                match ($type) {
                    self::TYPE_ACTIVE_SUBSTANCE     => $activeSubstances[$id]       = $label,
                    self::TYPE_PRESCRIPTION         => $prescriptionConditions[$id] = $label,
                    self::TYPE_TARGET_SPECIES       => $targetSpecies[$id]          = $label,
                    self::TYPE_ADMINISTRATION_ROUTE => $administrationRoutes[$id]   = $label,
                    self::TYPE_WITHDRAWAL_UNIT      => $withdrawalUnits[$id]        = $label,
                    default                         => null,
                };
            }
        }

        return new AnmvDictionaryCache(
            activeSubstances: $activeSubstances,
            prescriptionConditions: $prescriptionConditions,
            targetSpecies: $targetSpecies,
            administrationRoutes: $administrationRoutes,
            withdrawalUnits: $withdrawalUnits,
        );
    }
}
