<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
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
        'clinic_checkout'          => ['admissionId'],
        'clinic_invoice_details'   => ['invoiceId', 'per'],
        'clinic_invoices'          => ['q', 'per', 'statut', 'pay', 'vet', 'page', 'pp'],
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
        return $this->resolveOrNull($raw) ?? $this->urlGenerator->generate($defaultRoute, $defaultParams);
    }

    /**
     * The URL of a whitelisted target, or null when there is none to go back
     * to: absent, malformed, unknown route, raw URL, or missing / invalid
     * route parameters.
     */
    public function resolveOrNull(?string $raw): ?string
    {
        if (null === $raw || '' === trim($raw)) {
            return null;
        }

        if (!str_contains($raw, '|')) {
            // Tolerate a bare route name with no params, but never a raw URL.
            if (1 === preg_match('/^[a-z][a-z0-9_]*$/i', $raw) && \array_key_exists($raw, self::ALLOWED_ROUTES)) {
                return $this->generate($raw, []);
            }

            return null;
        }

        [$routeName, $paramString] = explode('|', $raw, 2);

        if (!\array_key_exists($routeName, self::ALLOWED_ROUTES)) {
            return null;
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

        return $this->generate($routeName, $filtered);
    }

    /**
     * The whitelisted route a target names, once it resolves; null otherwise.
     */
    public function routeOf(?string $raw): ?string
    {
        if (null === $this->resolveOrNull($raw)) {
            return null;
        }

        \assert(null !== $raw);

        return explode('|', $raw, 2)[0];
    }

    /**
     * @param array<string, string> $params
     */
    private function generate(string $routeName, array $params): ?string
    {
        try {
            // A route parameter that is missing or breaks its requirement is no target.
            return $this->urlGenerator->generate($routeName, $params);
        } catch (RoutingException) {
            return null;
        }
    }
}
