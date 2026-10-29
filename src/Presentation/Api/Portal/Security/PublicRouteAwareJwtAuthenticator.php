<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Authenticator\JWTAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\AccessMapInterface;

/**
 * lexik's authenticator, skipped on the routes access_control declares
 * public. lexik takes any Bearer header, and Symfony ignores `lazy` on a
 * stateless firewall: a stale access token sent to a public route (refresh,
 * codes, invitations) would otherwise answer 401 token_expired. The access
 * map stays the single source of what is public.
 */
class PublicRouteAwareJwtAuthenticator extends JWTAuthenticator
{
    private ?AccessMapInterface $accessMap = null;

    public function setAccessMap(AccessMapInterface $accessMap): void
    {
        $this->accessMap = $accessMap;
    }

    public function supports(Request $request): ?bool
    {
        [$attributes] = $this->accessMap?->getPatterns($request) ?? [null];

        if ([AuthenticatedVoter::PUBLIC_ACCESS] === $attributes) {
            return false;
        }

        return parent::supports($request);
    }
}
