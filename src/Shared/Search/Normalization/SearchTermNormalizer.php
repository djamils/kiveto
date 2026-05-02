<?php

declare(strict_types=1);

namespace App\Shared\Search\Normalization;

final class SearchTermNormalizer
{
    public function normalizeText(string $term): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $term);

        if (false === $transliterated) {
            throw new \InvalidArgumentException('Cannot transliterate term: invalid UTF-8 input.');
        }

        $lower     = strtolower($transliterated);
        $noHyphen  = (string) preg_replace('/[-\']+/', ' ', $lower);
        $collapsed = (string) preg_replace('/\s+/', ' ', $noHyphen);

        return trim($collapsed);
    }

    public function normalizePhone(string $term): ?string
    {
        $digits = preg_replace('/[^\d]/', '', $term);

        if (null === $digits) {
            return null;
        }

        if (10 === \strlen($digits) && str_starts_with($digits, '0')) {
            $digits = '+33' . substr($digits, 1);
        } elseif (!str_starts_with($digits, '+')) {
            $digits = '+' . $digits;
        }

        $digitsOnly = preg_replace('/[^\d]/', '', $digits);

        if (null === $digitsOnly || \strlen($digitsOnly) < 7) {
            return null;
        }

        return $digits;
    }

    public function isChipNumber(string $term): bool
    {
        return (bool) preg_match('/^\d{15}$/', $term);
    }

    public function isPhone(string $term): bool
    {
        $digits = preg_replace('/[^\d]/', '', $term);

        return null !== $digits && \strlen($digits) >= 7;
    }

    public function isExecutable(string $term): bool
    {
        return mb_strlen(trim($term)) >= 2;
    }
}
