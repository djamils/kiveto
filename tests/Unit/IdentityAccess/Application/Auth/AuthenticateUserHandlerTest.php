<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Auth;

use App\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserQuery;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\EmailNotVerifiedException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\InactiveUserException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\WrongContextException;
use App\IdentityAccess\Application\Query\AuthenticateUser\LoginContext;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\UserId;
use App\IdentityAccess\Domain\UserStatus;
use App\IdentityAccess\Domain\UserType;
use App\IdentityAccess\Infrastructure\Repository\InMemoryUserRepository;
use PHPUnit\Framework\TestCase;

final class AuthenticateUserHandlerTest extends TestCase
{
    private InMemoryUserRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new InMemoryUserRepository();
    }

    public function test_success_when_credentials_and_context_match(): void
    {
        $user = User::register(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            'clinic@example.com',
            'hashed-secret',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserType::CLINIC,
        );
        $this->repo->save($user);

        $handler = new AuthenticateUserHandler($this->repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return 'secret' === $plainPassword && 'hashed-secret' === $passwordHash;
            }
        });

        $identity = $handler(new AuthenticateUserQuery('clinic@example.com', 'secret', LoginContext::CLINIC));

        self::assertSame('clinic@example.com', $identity->email);
        self::assertSame('11111111-1111-1111-1111-111111111111', $identity->id);
        self::assertSame(UserType::CLINIC, $identity->type);
    }

    public function test_denies_wrong_password(): void
    {
        $user = User::register(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            'clinic@example.com',
            'hashed-secret',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserType::CLINIC,
        );
        $this->repo->save($user);

        $handler = new AuthenticateUserHandler($this->repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return false;
            }
        });

        $this->expectException(InvalidCredentialsException::class);
        $handler(new AuthenticateUserQuery('clinic@example.com', 'wrong', LoginContext::CLINIC));
    }

    public function test_denies_wrong_context(): void
    {
        $user = User::register(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            'clinic@example.com',
            'hashed-secret',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserType::CLINIC,
        );
        $this->repo->save($user);

        $handler = new AuthenticateUserHandler($this->repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return true;
            }
        });

        $this->expectException(WrongContextException::class);
        $handler(new AuthenticateUserQuery('clinic@example.com', 'secret', LoginContext::PORTAL));
    }

    public function test_denies_inactive_user(): void
    {
        $user = User::reconstitute(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            'clinic@example.com',
            'hashed-secret',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserStatus::DISABLED,
            null,
            UserType::CLINIC,
        );
        $this->repo->save($user);

        $handler = new AuthenticateUserHandler($this->repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return true;
            }
        });

        $this->expectException(InactiveUserException::class);
        $handler(new AuthenticateUserQuery('clinic@example.com', 'secret', LoginContext::CLINIC));
    }

    public function test_denies_portal_user_without_verified_email(): void
    {
        $user = User::reconstitute(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            'portal@example.com',
            'hashed-secret',
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            UserStatus::ACTIVE,
            null,
            UserType::PORTAL,
        );
        $this->repo->save($user);

        $handler = new AuthenticateUserHandler($this->repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return true;
            }
        });

        $this->expectException(EmailNotVerifiedException::class);
        $handler(new AuthenticateUserQuery('portal@example.com', 'secret', LoginContext::PORTAL));
    }
}

