<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\Security;

use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTExpiredEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * lexik's failures (missing, invalid or expired token, unknown owner) in the
 * API's error format: `401 token_expired` tells the app to refresh, any
 * other `401 unauthorized` to sign in again.
 */
final readonly class JwtProblemListener
{
    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    #[AsEventListener(event: Events::JWT_INVALID)]
    #[AsEventListener(event: Events::JWT_EXPIRED)]
    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function __invoke(AuthenticationFailureEvent $event): void
    {
        $event->setResponse(PortalProblemMap::response(
            $event instanceof JWTExpiredEvent ? PortalProblemMap::TOKEN_EXPIRED : PortalProblemMap::UNAUTHORIZED,
        ));
    }
}
