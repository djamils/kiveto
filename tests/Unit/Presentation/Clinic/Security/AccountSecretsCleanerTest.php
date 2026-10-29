<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\Security;

use App\Presentation\Clinic\Security\AccountSecretsCleaner;
use App\Presentation\Clinic\ViewModel\Auth\PublicAccountPage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class AccountSecretsCleanerTest extends TestCase
{
    public function testTheLogoutForgetsTheLinksOfTheSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(PublicAccountPage::RESET_SESSION_KEY, ['secret' => 's', 'issuedAt' => 1]);
        $session->set(PublicAccountPage::INVITATION_SESSION_KEY, ['secret' => 's', 'issuedAt' => 1]);
        $session->set('other', 'kept');
        $request = new Request();
        $request->setSession($session);

        new AccountSecretsCleaner()->onLogout(new LogoutEvent($request, null));

        self::assertSame(['other' => 'kept'], $session->all());
    }

    public function testARequestWithoutSessionIsLeftAlone(): void
    {
        $request = new Request();

        new AccountSecretsCleaner()->onLogout(new LogoutEvent($request, null));

        self::assertFalse($request->hasSession());
    }
}
