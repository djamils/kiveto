<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Resolver;

use App\Translation\Application\Port\AppScopeResolverInterface;
use App\Translation\Application\Port\LocaleResolverInterface;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class DefaultLocaleResolver implements LocaleResolverInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private AppScopeResolverInterface $scopeResolver,
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
            return Locale::fromString($this->normalizeCandidate($candidate));
        }

        return Locale::fromString($this->defaultLocale);
    }

    private function fromAcceptLanguage(?string $header): ?string
    {
        if (null === $header) {
            return null;
        }

        $parts = explode(',', $header);

        $language = trim($parts[0]);

        $semicolonPos = strpos($language, ';');

        if (false !== $semicolonPos) {
            $language = substr($language, 0, $semicolonPos);
        }

        return $language;
    }

    private function normalizeCandidate(string $candidate): string
    {
        $normalized = str_replace('-', '_', trim($candidate));
        $short      = mb_strtolower($normalized);

        return match ($short) {
            'fr' => 'fr_FR',
            'en' => 'en_GB',
            default => $normalized,
        };
    }
}
