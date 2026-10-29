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

        $normalized = \is_string($candidate) ? $this->normalizeCandidate($candidate) : null;

        // A header the app does not serve (« de », « *», « en-us »…) falls
        // back to the default instead of failing the request.
        return Locale::fromString($normalized ?? $this->defaultLocale);
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

    /** « fr », « fr_fr » or « FR-fr » → « fr-FR »; null when it is no locale at all. */
    private function normalizeCandidate(string $candidate): ?string
    {
        $short = mb_strtolower(str_replace('_', '-', trim($candidate)));

        if (isset($this->shortLocaleMap[$short])) {
            return $this->shortLocaleMap[$short];
        }

        if (1 !== preg_match('/^([a-z]{2})-([a-z]{2})$/', $short, $parts)) {
            return null;
        }

        return $parts[1] . '-' . mb_strtoupper($parts[2]);
    }
}
