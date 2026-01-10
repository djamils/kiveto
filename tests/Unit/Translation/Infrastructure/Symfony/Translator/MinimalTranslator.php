<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Symfony\Translator;

use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Minimal translator without TranslatorBagInterface (used to test guard clauses).
 */
final class MinimalTranslator implements TranslatorInterface, LocaleAwareInterface
{
    private string $locale;

    public function __construct(string $locale = 'fr-FR')
    {
        $this->locale = $locale;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $id . '@' . ($locale ?? $this->locale);
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
