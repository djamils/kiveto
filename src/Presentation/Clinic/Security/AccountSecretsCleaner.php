<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Security;

use App\Presentation\Clinic\ViewModel\Auth\PublicAccountPage;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * A reset or invitation secret kept in the session by a public account page
 * must not outlive the visit: the next person signing in on a shared
 * workstation — or the one signing out — leaves no usable link behind. The
 * session survives a sign-in (it is migrated, not emptied), hence this.
 *
 * Only the clinic firewall is listened to: the secrets live in the session of
 * the clinic host, while the stateless Portal API firewall authenticates every
 * call with its access token — a successful sign-in each time — and a
 * stateless request must not use the session (a 500 in debug mode).
 */
final readonly class AccountSecretsCleaner
{
    private const string CLINIC_FIREWALL_DISPATCHER = 'security.event_dispatcher.clinic';

    #[AsEventListener(event: LoginSuccessEvent::class, dispatcher: self::CLINIC_FIREWALL_DISPATCHER)]
    public function onLogin(LoginSuccessEvent $event): void
    {
        self::forget($event->getRequest());
    }

    #[AsEventListener(event: LogoutEvent::class, dispatcher: self::CLINIC_FIREWALL_DISPATCHER)]
    public function onLogout(LogoutEvent $event): void
    {
        self::forget($event->getRequest());
    }

    private static function forget(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        foreach (PublicAccountPage::SESSION_KEYS as $key) {
            $request->getSession()->remove($key);
        }
    }
}
