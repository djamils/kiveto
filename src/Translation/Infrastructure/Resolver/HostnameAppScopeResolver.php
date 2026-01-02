<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Resolver;

use App\Translation\Application\Port\AppScopeResolverInterface;
use App\Translation\Domain\ValueObject\AppScope;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class HostnameAppScopeResolver implements AppScopeResolverInterface
{
    public function __construct(
        private RequestStack $requestStack,
        /** @var array<string, AppScope> */
        private array $hostMap = [
            'clinic.kiveto.local'     => AppScope::CLINIC,
            'portal.kiveto.local'     => AppScope::PORTAL,
            'backoffice.kiveto.local' => AppScope::BACKOFFICE,
        ],
    ) {
    }

    public function resolve(): AppScope
    {
        $host = $this->requestStack->getCurrentRequest()?->getHost();

        if (null !== $host) {
            $mapped = $this->hostMap[$host] ?? null;

            if ($mapped instanceof AppScope) {
                return $mapped;
            }
        }

        return AppScope::SHARED;
    }
}
