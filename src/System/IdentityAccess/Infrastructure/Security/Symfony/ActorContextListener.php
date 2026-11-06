<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\Shared\Application\Security\ActorContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Tells the message buses who is acting: the signed-in account of the
 * request, after the firewall (priority 8) has authenticated it. A request
 * without one (public page, Portal API before its authenticator) leaves the
 * actor unset.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 4)]
final readonly class ActorContextListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private ActorContext $actorContext,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();

        $this->actorContext->set($user instanceof SecurityUser ? $user->id() : null);
    }
}
