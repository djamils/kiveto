<?php

declare(strict_types=1);

namespace App\Context\Client\Domain\TaxProfile;

/**
 * Brings a legal name and the registry's official name to a comparable form,
 * so that « Élevage des Calanques » and « EARL ELEVAGE DES CALANQUES » match.
 */
final class LegalNameNormalizer
{
    private const array ARTICLES = ['le', 'la', 'les', 'l'];

    private const array LEGAL_FORMS = [
        'sas', 'sasu', 'sarl', 'eurl', 'earl', 'gaec', 'scea', 'sa', 'sci', 'snc', 'ei', 'association',
    ];

    public static function normalize(string $name): string
    {
        $text       = mb_strtolower($name);
        $decomposed = \Normalizer::normalize($text, \Normalizer::FORM_D);
        $text       = \is_string($decomposed) ? (string) preg_replace('/\p{Mn}+/u', '', $decomposed) : $text;
        $text       = str_replace('&', ' et ', $text);
        $text       = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        $tokens = array_values(array_filter(explode(' ', $text), static fn (string $token): bool => '' !== $token));

        // One legal form, at the start or else at the end, never the whole name.
        if (\count($tokens) > 1 && \in_array($tokens[0], self::LEGAL_FORMS, true)) {
            array_shift($tokens);
        } elseif (\count($tokens) > 1 && \in_array($tokens[\count($tokens) - 1], self::LEGAL_FORMS, true)) {
            array_pop($tokens);
        }

        if (\count($tokens) > 1 && \in_array($tokens[0], self::ARTICLES, true)) {
            array_shift($tokens);
        }

        return implode(' ', $tokens);
    }
}
