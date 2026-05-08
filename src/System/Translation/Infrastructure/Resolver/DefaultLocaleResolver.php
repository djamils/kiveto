<?php

declare(strict_types=1);

namespace App\System\Translation\Infrastructure\Resolver;

use App\Shared\Domain\Localization\Locale;
use App\System\Translation\Application\Port\AppScopeResolverInterface;
use App\System\Translation\Application\Port\LocaleResolverInterface;
use App\System\Translation\Domain\ValueObject\AppScope;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class DefaultLocaleResolver implements LocaleResolverInterface
{
    /**
     * @param array<string, string> $shortLocaleMap
     */
    public function __construct(
        private RequestStack $requestStack,
        private AppScopeResolverInterface $scopeResolver,
        private string $defaultLocale,
        private string $backofficeLocale,
        private array $shortLocaleMap,
    ) {
    }

    public function resolve(): Locale
    {
        $scope = $this->scopeResolver->resolve();

        if (AppScope::BACKOFFICE === $scope) {
            return Locale::fromString($this->backofficeLocale);
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
        $normalized = str_replace('_', '-', trim($candidate));
        $short      = mb_strtolower($normalized);

        return $this->shortLocaleMap[$short] ?? $normalized;
    }
}
