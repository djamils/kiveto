<?php

declare(strict_types=1);

namespace App\Translation\Domain\ValueObject;

final class TranslationCatalogId
{
    public function __construct(
        private AppScope $scope,
        private Locale $locale,
        private TranslationDomain $domain,
    ) {
    }

    public static function fromStrings(string $scope, string $locale, string $domain): self
    {
        return new self(
            AppScope::fromString($scope),
            Locale::fromString($locale),
            TranslationDomain::fromString($domain),
        );
    }

    public function scope(): AppScope
    {
        return $this->scope;
    }

    public function locale(): Locale
    {
        return $this->locale;
    }

    public function domain(): TranslationDomain
    {
        return $this->domain;
    }

    public function cacheKeyPart(): string
    {
        return \sprintf(
            '%s:%s:%s',
            $this->scope->value,
            $this->locale->toString(),
            $this->domain->toString(),
        );
    }
}
