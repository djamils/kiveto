<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Security;

use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * No console page without a second factor: a back-office session that did
 * not pass it (opened before the second factor existed, or by any other
 * way in) is closed and sent to the sign-in.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 6)]
final readonly class SecondFactorGuard
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $user    = $this->tokenStorage->getToken()?->getUser();

        if (!$event->isMainRequest() || !$user instanceof SecurityUser || UserType::BACKOFFICE !== $user->type() || !$request->hasSession()) {
            return;
        }

        if (\is_int($request->getSession()->get(BackofficeAuthenticator::VERIFIED_AT))) {
            return;
        }

        $this->tokenStorage->setToken(null);
        $request->getSession()->invalidate();
        $event->setResponse(new RedirectResponse($this->urls->generate('backoffice_login')));
    }
}
