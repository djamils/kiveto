<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Resolver;

use App\Translation\Application\Port\AppScopeResolver;
use App\Translation\Application\Port\LocaleResolver;
use App\Translation\Domain\Model\ValueObject\AppScope;
use App\Translation\Domain\Model\ValueObject\Locale;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class DefaultLocaleResolver implements LocaleResolver
{
    public function __construct(
        private RequestStack $requestStack,
        private AppScopeResolver $scopeResolver,
        private string $defaultLocale = 'fr_FR',
    ) {
    }

    public function resolve(): Locale
    {
        $scope = $this->scopeResolver->resolve();

        if (AppScope::BACKOFFICE === $scope) {
            return Locale::fromString('fr_FR');
        }

        $request = $this->requestStack->getCurrentRequest();

        $candidate = $request?->attributes->get('_locale')
            ?? $request?->query->get('_locale')
            ?? $this->fromAcceptLanguage($request?->headers->get('Accept-Language'));

        if (\is_string($candidate) && '' !== trim($candidate)) {
            $normalized = str_replace('-', '_', $candidate);

            return Locale::fromString($normalized);
        }

        return Locale::fromString($this->defaultLocale);
    }

    private function fromAcceptLanguage(?string $header): ?string
    {
        if (null === $header) {
            return null;
        }

        $parts = explode(',', $header);

        if ([] === $parts) {
            return null;
        }

        $language = trim($parts[0]);

        $semicolonPos = strpos($language, ';');

        if (false !== $semicolonPos) {
            $language = substr($language, 0, $semicolonPos);
        }

        return $language;
    }
}
