<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

/** Comparing people's names the way staff read them. */
final class PersonName
{
    /**
     * The same last name, case, accents and spacing ignored (« Lefèvre » is
     * « LEFEVRE », « Le  Gall » is « le gall »).
     */
    public static function sameLastName(string $a, string $b): bool
    {
        return self::folded($a) === self::folded($b);
    }

    private static function folded(string $name): string
    {
        $decomposed = \Normalizer::normalize($name, \Normalizer::FORM_D);
        $bare       = preg_replace(['/\p{Mn}+/u', '/\s+/u'], ['', ' '], \is_string($decomposed) ? $decomposed : $name);

        return mb_strtolower(trim($bare ?? $name));
    }
}
