<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Infrastructure\Security\Symfony;

use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\EndUserSession\EndUserSession;
use App\System\IdentityAccess\Application\Command\TrackUserSession\TrackUserSession;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\SecurityUser;
use App\System\IdentityAccess\Infrastructure\Security\Symfony\UserSessionListener;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class UserSessionListenerTest extends TestCase
{
    private const string USER       = '01960000-0000-7000-8000-000000005c01';
    private const string SESSION_ID = 'php-session-id';

    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-11-06 09:00:00', new \DateTimeZone('UTC')));
    }

    #[DataProvider('provideTheSessionIsOpenedAtTheSignInCases')]
    public function testTheSessionIsOpenedAtTheSignIn(UserType $type): void
    {
        $request = $this->request(started: true);
        $bus     = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TrackUserSession(self::USER, self::SESSION_ID, 'Firefox', '203.0.113.7'))
            ->willReturn(true)
        ;

        $this->listener(new TokenStorage(), $bus)->onLogin($this->loginEvent(new SecurityUser(self::USER, 'staff@kiveto.fr', $type), $request));

        self::assertSame($this->clock->now()->getTimestamp(), $request->getSession()->get(UserSessionListener::CHECKED_AT), 'the next request is not checked again');
    }

    /**
     * @return iterable<string, array{UserType}>
     */
    public static function provideTheSessionIsOpenedAtTheSignInCases(): iterable
    {
        yield 'clinic staff' => [UserType::CLINIC];

        yield 'back office' => [UserType::BACKOFFICE];
    }

    public function testASignInWithoutATrackableSessionOpensNone(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $listener = $this->listener(new TokenStorage(), $bus);

        $listener->onLogin($this->loginEvent(new SecurityUser(self::USER, null, UserType::PORTAL), $this->request(started: true)));
        $listener->onLogin($this->loginEvent(new InMemoryUser('dev', null), $this->request(started: true)));
        $listener->onLogin($this->loginEvent($this->staff(), Request::create('/login')));
        $listener->onLogin($this->loginEvent($this->staff(), $this->request(started: false)));
    }

    public function testARequestOfALiveSessionIsTrackedAtMostEveryThirtySeconds(): void
    {
        $request = $this->request(started: true);
        $request->getSession()->set(UserSessionListener::CHECKED_AT, $this->clock->now()->getTimestamp() - 30);
        $storage = $this->signedIn($this->staff());
        $bus     = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())->method('dispatch')
            ->with(new TrackUserSession(self::USER, self::SESSION_ID, 'Firefox', '203.0.113.7'))
            ->willReturn(true)
        ;
        $listener = $this->listener($storage, $bus);

        $event = $this->requestEvent($request);
        $listener->onRequest($event);

        self::assertNull($event->getResponse());
        self::assertNotNull($storage->getToken());
        self::assertSame($this->clock->now()->getTimestamp(), $request->getSession()->get(UserSessionListener::CHECKED_AT));

        // Checked just now: the next request costs nothing.
        $listener->onRequest($this->requestEvent($request));
    }

    public function testASessionCheckedLessThanThirtySecondsAgoIsNotTracked(): void
    {
        $request = $this->request(started: true);
        $request->getSession()->set(UserSessionListener::CHECKED_AT, $this->clock->now()->getTimestamp() - 29);
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->listener($this->signedIn($this->staff()), $bus)->onRequest($this->requestEvent($request));
    }

    public function testASessionRevokedFromAnotherBrowserIsEndedHere(): void
    {
        $request = $this->request(started: true);
        $request->getSession()->set('_security_backoffice', 'serialized token');
        $storage = $this->signedIn($this->staff());
        $bus     = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->willReturn(false);

        $event = $this->requestEvent($request);
        $this->listener($storage, $bus)->onRequest($event);

        self::assertNull($storage->getToken());
        self::assertNotSame(self::SESSION_ID, $request->getSession()->getId(), 'the session id is regenerated');
        self::assertSame([], $request->getSession()->all(), 'the session is emptied');
        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('https://console.kiveto.test/login', $response->getTargetUrl());
    }

    public function testARequestWithoutATrackableSessionIsNotTracked(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->listener($this->signedIn($this->staff()), $bus)->onRequest($this->requestEvent($this->request(started: true), HttpKernelInterface::SUB_REQUEST));
        $this->listener(new TokenStorage(), $bus)->onRequest($this->requestEvent($this->request(started: true)));
        $this->listener($this->signedIn(new InMemoryUser('dev', null)), $bus)->onRequest($this->requestEvent($this->request(started: true)));
        $this->listener($this->signedIn(new SecurityUser(self::USER, null, UserType::PORTAL)), $bus)->onRequest($this->requestEvent($this->request(started: true)));
        $this->listener($this->signedIn($this->staff()), $bus)->onRequest($this->requestEvent(Request::create('/console')));

        $session = $this->createStub(SessionInterface::class);
        $session->method('getId')->willReturn('');
        $request = Request::create('/console');
        $request->setSession($session);
        $this->listener($this->signedIn($this->staff()), $bus)->onRequest($this->requestEvent($request));
    }

    public function testTheSignedOutSessionIsEnded(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::once())->method('dispatch')->with(new EndUserSession(self::SESSION_ID));

        $this->listener(new TokenStorage(), $bus)->onLogout(new LogoutEvent($this->request(started: true), new UsernamePasswordToken($this->staff(), 'backoffice')));
    }

    public function testASignOutWithoutATrackedSessionEndsNone(): void
    {
        $bus = $this->createMock(CommandBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $listener = $this->listener(new TokenStorage(), $bus);

        $listener->onLogout(new LogoutEvent($this->request(started: true), null));
        $listener->onLogout(new LogoutEvent($this->request(started: true), new UsernamePasswordToken(new InMemoryUser('dev', null), 'dev')));
        $listener->onLogout(new LogoutEvent(Request::create('/logout'), new UsernamePasswordToken($this->staff(), 'backoffice')));
        $listener->onLogout(new LogoutEvent($this->request(started: false), new UsernamePasswordToken($this->staff(), 'backoffice')));
    }

    private function listener(TokenStorage $storage, CommandBusInterface $bus): UserSessionListener
    {
        return new UserSessionListener($storage, $bus, $this->clock);
    }

    private function staff(): SecurityUser
    {
        return new SecurityUser(self::USER, 'admin@kiveto.fr', UserType::BACKOFFICE);
    }

    private function signedIn(UserInterface $user): TokenStorage
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken($user, 'backoffice'));

        return $storage;
    }

    /**
     * A request of the console with a session, started (it has its id) or
     * not yet.
     */
    private function request(bool $started): Request
    {
        $storage = new MockArraySessionStorage();
        $session = new Session($storage);

        if ($started) {
            $storage->setId(self::SESSION_ID);
            $session->start();
        }

        $request = Request::create('https://console.kiveto.test/utilisateurs', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'Firefox']);
        $request->setSession($session);

        return $request;
    }

    private function requestEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
    }

    private function loginEvent(UserInterface $user, Request $request): LoginSuccessEvent
    {
        return new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('someone', static fn (): UserInterface => $user)),
            new UsernamePasswordToken($user, 'backoffice'),
            $request,
            null,
            'backoffice',
        );
    }
}
