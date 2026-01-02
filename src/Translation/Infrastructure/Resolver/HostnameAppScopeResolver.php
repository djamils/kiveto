<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Resolver;

use App\Translation\Application\Port\AppScopeResolver;
use App\Translation\Domain\Model\ValueObject\AppScope;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class HostnameAppScopeResolver implements AppScopeResolver
{
    public function __construct(
        private RequestStack $requestStack,
        private array $hostMap = [
            'clinic.kiveto.com'     => AppScope::CLINIC,
            'portal.kiveto.com'     => AppScope::PORTAL,
            'backoffice.kiveto.com' => AppScope::BACKOFFICE,
        ],
    ) {
    }

    public function resolve(): AppScope
    {
        $host = $this->requestStack->getCurrentRequest()?->getHost();

        if (null !== $host && isset($this->hostMap[$host])) {
            return $this->hostMap[$host];
        }

        return AppScope::SHARED;
    }
}
