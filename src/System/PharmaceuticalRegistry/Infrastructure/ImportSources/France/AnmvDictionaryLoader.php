<?php

declare(strict_types=1);

namespace App\System\PharmaceuticalRegistry\Infrastructure\ImportSources\France;

final class AnmvDictionaryLoader
{
    public function load(string $filePath): AnmvDictionaryCache
    {
        $xml = new \SimpleXMLElement(file_get_contents($filePath) ?: '');

        /** @var array<string, array<int, string>> $data */
        $data = [];

        foreach ($xml->children() as $sectionName => $sectionEl) {
            foreach ($sectionEl->entry as $entry) {
                $code                      = (int) (string) $entry->{'source-code'};
                $label                     = (string) $entry->{'source-desc'};
                $data[$sectionName][$code] = $label;
            }
        }

        return new AnmvDictionaryCache($data);
    }
}
