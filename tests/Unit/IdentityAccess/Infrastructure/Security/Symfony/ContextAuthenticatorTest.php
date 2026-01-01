<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Infrastructure\Security\Symfony;

use App\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Domain\ValueObject\UserType;
use App\IdentityAccess\Infrastructure\Security\Symfony\ContextAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class ContextAuthenticatorTest extends TestCase
{
    public function testSupportsOnlyLoginPost(): void
    {
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));

        self::assertTrue($authenticator->supports(Request::create('/login', 'POST')));
        self::assertFalse($authenticator->supports(Request::create('/login', 'GET')));
        self::assertFalse($authenticator->supports(Request::create('/other', 'POST')));
    }

    public function testAuthenticateReturnsPassport(): void
    {
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: json_encode(['email' => 'user@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );

        $passport = $authenticator->authenticate($request);

        $userBadge = $passport->getBadge(UserBadge::class);
        self::assertNotNull($userBadge);
        self::assertSame('11111111-1111-1111-1111-111111111111', $userBadge->getUserIdentifier());
    }

    public function testAuthenticateThrowsOnInvalidJson(): void
    {
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: '{bad json',
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnInvalidPayloadStructure(): void
    {
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: json_encode('not-an-array', \JSON_THROW_ON_ERROR),
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnContextError(): void
    {
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));
        $request       = Request::create(
            'https://unknown.example/login',
            'POST',
            server: ['HTTP_HOST' => 'unknown.example'],
            content: json_encode(['email' => 'user@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testOnAuthenticationFailureForDomainException(): void
    {
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));
        $request       = Request::create('https://clinic.example/login', 'POST');
        $response      = $authenticator->onAuthenticationFailure(
            $request,
            new AuthenticationException(previous: new InvalidCredentialsException()),
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('INVALID_CREDENTIALS', (string) $response->getContent());
    }

    public function testOnAuthenticationFailureDefault(): void
    {
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));
        $request       = Request::create('https://clinic.example/login', 'POST');
        $response      = $authenticator->onAuthenticationFailure($request, new AuthenticationException());

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertStringContainsString('AUTHENTICATION_FAILED', (string) $response->getContent());
    }

    public function testOnAuthenticationSuccess(): void
    {
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));
        $response      = $authenticator->onAuthenticationSuccess(
            Request::create('/login', 'POST'),
            $this->createStub(\Symfony\Component\Security\Core\Authentication\Token\TokenInterface::class),
            'main',
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAuthenticateThrowsOnInvalidCredentialsPayload(): void
    {
        $authenticator = new ContextAuthenticator($this->handlerFor(UserType::CLINIC));
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: json_encode(['email' => '', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsOnAuthenticationDeniedWrapped(): void
    {
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null); // will trigger InvalidCredentialsException

        $verifier = $this->createStub(PasswordHashVerifierInterface::class);
        $handler  = new AuthenticateUserHandler($repo, $verifier);

        $authenticator = new ContextAuthenticator($handler);
        $request       = Request::create(
            'https://clinic.example/login',
            'POST',
            server: ['HTTP_HOST' => 'clinic.example'],
            content: json_encode(['email' => 'user@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    public function testAuthenticateSupportsPortalAndBackofficeContexts(): void
    {
        $authenticatorPortal = new ContextAuthenticator($this->handlerFor(UserType::PORTAL));
        $passportPortal      = $authenticatorPortal->authenticate(Request::create(
            'https://portal.example/login',
            'POST',
            server: ['HTTP_HOST' => 'portal.example'],
            content: json_encode(['email' => 'portal@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        ));

        self::assertNotNull($passportPortal->getBadge(UserBadge::class));

        $authenticatorBo = new ContextAuthenticator($this->handlerFor(UserType::BACKOFFICE));
        $passportBo      = $authenticatorBo->authenticate(Request::create(
            'https://backoffice.example/login',
            'POST',
            server: ['HTTP_HOST' => 'backoffice.example'],
            content: json_encode(['email' => 'bo@example.com', 'password' => 'secret'], \JSON_THROW_ON_ERROR),
        ));

        self::assertNotNull($passportBo->getBadge(UserBadge::class));
    }

    private function handlerFor(UserType $type): AuthenticateUserHandler
    {
        $user = User::reconstitute(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            match ($type) {
                UserType::CLINIC     => 'user@example.com',
                UserType::PORTAL     => 'portal@example.com',
                UserType::BACKOFFICE => 'bo@example.com',
            },
            '$hash',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserStatus::ACTIVE,
            new \DateTimeImmutable('2025-01-02T10:00:00+00:00'),
            $type,
        );

        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn($user);

        $verifier = new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return 'secret' === $plainPassword && '$hash' === $passwordHash;
            }
        };

        return new AuthenticateUserHandler($repo, $verifier);
    }
}
