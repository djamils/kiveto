<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\Security;

use App\Presentation\Clinic\Security\AccountSecretsCleaner;
use App\Presentation\Clinic\ViewModel\Auth\PublicAccountPage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class AccountSecretsCleanerTest extends TestCase
{
    public function testTheSignInForgetsTheLinksOfTheSession(): void
    {
        $request = $this->requestWithLinks();

        new AccountSecretsCleaner()->onLogin(new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('vet@kiveto.local')),
            $this->createStub(TokenInterface::class),
            $request,
            null,
            'clinic',
        ));

        self::assertSame(['other' => 'kept'], $request->getSession()->all());
    }

    public function testTheLogoutForgetsTheLinksOfTheSession(): void
    {
        $request = $this->requestWithLinks();

        new AccountSecretsCleaner()->onLogout(new LogoutEvent($request, null));

        self::assertSame(['other' => 'kept'], $request->getSession()->all());
    }

    public function testARequestWithoutSessionIsLeftAlone(): void
    {
        $request = new Request();

        new AccountSecretsCleaner()->onLogout(new LogoutEvent($request, null));

        self::assertFalse($request->hasSession());
    }

    /**
     * The links live in the session of the clinic host. The stateless Portal
     * API firewall authenticates every call with its access token, each a
     * successful sign-in: listening to every firewall used the session of a
     * stateless request, which Symfony refuses (500 in debug mode).
     */
    public function testItListensToTheClinicFirewallOnly(): void
    {
        foreach (['onLogin', 'onLogout'] as $method) {
            $reflection = new \ReflectionMethod(AccountSecretsCleaner::class, $method);
            $listeners  = $reflection->getAttributes(AsEventListener::class);

            self::assertCount(1, $listeners, $method);
            self::assertSame('security.event_dispatcher.clinic', $listeners[0]->newInstance()->dispatcher, $method);
        }
    }

    private function requestWithLinks(): Request
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(PublicAccountPage::RESET_SESSION_KEY, ['secret' => 's', 'issuedAt' => 1]);
        $session->set(PublicAccountPage::INVITATION_SESSION_KEY, ['secret' => 's', 'issuedAt' => 1]);
        $session->set('other', 'kept');
        $request = new Request();
        $request->setSession($session);

        return $request;
    }
}
