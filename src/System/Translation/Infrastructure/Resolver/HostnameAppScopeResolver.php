<?php

declare(strict_types=1);

namespace App\System\Translation\Infrastructure\Resolver;

use App\System\Translation\Application\Port\AppScopeResolverInterface;
use App\System\Translation\Domain\ValueObject\AppScope;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The application of the current request, from its host: `clinic.`,
 * `portal.` or `backoffice.` followed by the domain of the deployment
 * (KIVETO_DOMAIN). Any other host, or no request, is the shared scope.
 */
final readonly class HostnameAppScopeResolver implements AppScopeResolverInterface
{
    private const array SCOPES = [
        'clinic'     => AppScope::CLINIC,
        'portal'     => AppScope::PORTAL,
        'backoffice' => AppScope::BACKOFFICE,
    ];

    public function __construct(
        private RequestStack $requestStack,
        private string $domain,
    ) {
    }

    public function resolve(): AppScope
    {
        $host = $this->requestStack->getCurrentRequest()?->getHost();

        if (null !== $host) {
            [$app, $domain] = array_pad(explode('.', $host, 2), 2, '');

            if ($domain === $this->domain && isset(self::SCOPES[$app])) {
                return self::SCOPES[$app];
            }
        }

        return AppScope::SHARED;
    }
}
