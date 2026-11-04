<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Routing;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * `%env(kiveto_domain:KIVETO_DOMAIN)%`: the domain this deployment runs on,
 * its hosts being `clinic.`, `portal.` and `backoffice.<domain>`. A value
 * outside `kiveto.domain_pattern` (config/services.yaml), the requirement of
 * the routes' `{kiveto_domain}`, is refused when first read: the application
 * fails instead of generating URLs that no route of this deployment matches.
 */
final readonly class KivetoDomainEnvVarProcessor implements EnvVarProcessorInterface
{
    public function __construct(
        #[Autowire(param: 'kiveto.domain_pattern')]
        private string $pattern,
    ) {
    }

    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $domain = $getEnv($name);

        if (!\is_string($domain) || 1 !== preg_match('{^(?:' . $this->pattern . ')$}D', $domain)) {
            throw new RuntimeException(\sprintf(
                'Env var "%s" must be one of the Kiveto domains (%s), "%s" given.',
                $name,
                stripslashes($this->pattern),
                \is_string($domain) ? $domain : get_debug_type($domain),
            ));
        }

        return $domain;
    }

    public static function getProvidedTypes(): array
    {
        return ['kiveto_domain' => 'string'];
    }
}
