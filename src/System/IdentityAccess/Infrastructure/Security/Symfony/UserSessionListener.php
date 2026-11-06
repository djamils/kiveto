<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\EndUserSession\EndUserSession;
use App\System\IdentityAccess\Application\Command\TrackUserSession\TrackUserSession;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Lists the signed-in browsers of staff and back-office accounts
 * (« Sessions actives »): a request of such a session is tracked at most
 * every 30 seconds; a session revoked from another browser is ended there
 * and its holder sent back to the sign-in page. The session is opened at
 * the sign-in itself, after Symfony gave it its new id, so that the first
 * page costs nothing. Owner (Portal) accounts are not tracked: their API
 * sessions are refresh-token families.
 */
final readonly class UserSessionListener
{
    public const string CHECKED_AT = '_identity_access.session_checked_at';

    private const int CHECK_SECONDS = 30;

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private CommandBusInterface $commandBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * After SessionStrategyListener (priority 0) migrated the session id.
     */
    #[AsEventListener(event: LoginSuccessEvent::class, priority: -64)]
    public function onLogin(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $user    = $event->getUser();

        if (!$user instanceof SecurityUser || UserType::PORTAL === $user->type() || !$request->hasSession() || '' === $request->getSession()->getId()) {
            return;
        }

        $this->commandBus->dispatch(new TrackUserSession(
            userId: $user->id(),
            sessionId: $request->getSession()->getId(),
            userAgent: $request->headers->get('User-Agent'),
            ipAddress: $request->getClientIp(),
        ));
        $request->getSession()->set(self::CHECKED_AT, $this->clock->now()->getTimestamp());
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $user    = $this->tokenStorage->getToken()?->getUser();

        if (!$event->isMainRequest() || !$user instanceof SecurityUser || UserType::PORTAL === $user->type() || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $now     = $this->clock->now()->getTimestamp();
        $checked = $session->get(self::CHECKED_AT);

        if ('' === $session->getId() || (\is_int($checked) && $now - $checked < self::CHECK_SECONDS)) {
            return;
        }

        $alive = $this->commandBus->dispatch(new TrackUserSession(
            userId: $user->id(),
            sessionId: $session->getId(),
            userAgent: $request->headers->get('User-Agent'),
            ipAddress: $request->getClientIp(),
        ));

        if (true === $alive) {
            $session->set(self::CHECKED_AT, $now);

            return;
        }

        $this->tokenStorage->setToken(null);
        $session->invalidate();
        $event->setResponse(new RedirectResponse($request->getUriForPath('/login')));
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->getToken()?->getUser() instanceof SecurityUser || !$request->hasSession() || '' === $request->getSession()->getId()) {
            return;
        }

        $this->commandBus->dispatch(new EndUserSession($request->getSession()->getId()));
    }
}
