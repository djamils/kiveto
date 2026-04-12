<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Resolves a serialized "return to" target into a safe URL.
 *
 * Format: routeName|key1=val1&key2=val2
 *
 * Whitelisted by route name + allowed param names. Raw URLs are NEVER accepted.
 * Falls back to the default route on any malformed, unknown, or open-redirect input.
 */
final readonly class ReturnToResolver
{
    /**
     * Routes that may legally appear as the target of a `_returnTo` form field.
     *
     * Public so the drift-detection test can iterate it without poking at internals.
     *
     * @var array<string, list<string>>
     */
    public const array ALLOWED_ROUTES = [
        'clinic_clients_list'      => ['tab', 'search', 'page'],
        'clinic_scheduling_agenda' => ['date', 'view'],
    ];

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param array<string, scalar> $defaultParams
     */
    public function resolve(?string $raw, string $defaultRoute, array $defaultParams = []): string
    {
        $fallback = $this->urlGenerator->generate($defaultRoute, $defaultParams);

        if (null === $raw || '' === trim($raw)) {
            return $fallback;
        }

        if (!str_contains($raw, '|')) {
            // Tolerate a bare route name with no params, but never a raw URL.
            if (1 === preg_match('/^[a-z][a-z0-9_]*$/i', $raw) && \array_key_exists($raw, self::ALLOWED_ROUTES)) {
                return $this->urlGenerator->generate($raw);
            }

            return $fallback;
        }

        [$routeName, $paramString] = explode('|', $raw, 2);

        if (!\array_key_exists($routeName, self::ALLOWED_ROUTES)) {
            return $fallback;
        }

        $allowedParams = self::ALLOWED_ROUTES[$routeName];
        $rawParams     = [];
        parse_str($paramString, $rawParams);

        $filtered = [];
        foreach ($rawParams as $key => $value) {
            if (\in_array($key, $allowedParams, true) && \is_scalar($value)) {
                $filtered[$key] = (string) $value;
            }
        }

        return $this->urlGenerator->generate($routeName, $filtered);
    }
}
