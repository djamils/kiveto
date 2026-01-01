<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Query\AuthenticateUser;

use App\IdentityAccess\Application\Port\Security\PasswordHashVerifierInterface;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserHandler;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticateUserQuery;
use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\AccountStatusNotAllowedException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\AuthenticationContextMismatchException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\EmailVerificationRequiredException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\IdentityAccess\Domain\User;
use App\IdentityAccess\Domain\ValueObject\UserId;
use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\TestCase;

final class AuthenticateUserHandlerTest extends TestCase
{
    public function testSuccessWhenCredentialsAndContextMatch(): void
    {
        $user = $this->registeredUser(UserType::CLINIC, 'clinic@example.com', 'hashed-secret');

        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturnMap([
            ['clinic@example.com', $user],
        ]);

        $handler = new AuthenticateUserHandler($repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return 'secret' === $plainPassword && 'hashed-secret' === $passwordHash;
            }
        });

        $identity = $handler(new AuthenticateUserQuery('clinic@example.com', 'secret', AuthenticationContext::CLINIC));

        self::assertSame('clinic@example.com', $identity->email);
        self::assertSame('11111111-1111-1111-1111-111111111111', $identity->id);
        self::assertSame(UserType::CLINIC, $identity->type);
        self::assertSame(['ROLE_USER'], $identity->roles);
    }

    public function testDeniesWrongPassword(): void
    {
        $user = $this->registeredUser(UserType::CLINIC, 'clinic@example.com', 'hashed-secret');
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn($user);

        $handler = new AuthenticateUserHandler($repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return false;
            }
        });

        $this->expectException(InvalidCredentialsException::class);
        $handler(new AuthenticateUserQuery('clinic@example.com', 'wrong', AuthenticationContext::CLINIC));
    }

    public function testDeniesWhenUserNotFound(): void
    {
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn(null);

        $handler = new AuthenticateUserHandler($repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return true;
            }
        });

        $this->expectException(InvalidCredentialsException::class);
        $handler(new AuthenticateUserQuery('missing@example.com', 'secret', AuthenticationContext::CLINIC));
    }

    public function testDeniesWrongContext(): void
    {
        $user = $this->registeredUser(UserType::CLINIC, 'clinic@example.com', 'hashed-secret');
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn($user);

        $handler = new AuthenticateUserHandler($repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return true;
            }
        });

        $this->expectException(AuthenticationContextMismatchException::class);
        $handler(new AuthenticateUserQuery('clinic@example.com', 'secret', AuthenticationContext::PORTAL));
    }

    public function testDeniesInactiveUser(): void
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
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn($user);

        $handler = new AuthenticateUserHandler($repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return true;
            }
        });

        $this->expectException(AccountStatusNotAllowedException::class);
        $handler(new AuthenticateUserQuery('clinic@example.com', 'secret', AuthenticationContext::CLINIC));
    }

    public function testDeniesPortalUserWithoutVerifiedEmail(): void
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
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByEmail')->willReturn($user);

        $handler = new AuthenticateUserHandler($repo, new class implements PasswordHashVerifierInterface {
            public function verify(string $plainPassword, string $passwordHash): bool
            {
                return true;
            }
        });

        $this->expectException(EmailVerificationRequiredException::class);
        $handler(new AuthenticateUserQuery('portal@example.com', 'secret', AuthenticationContext::PORTAL));
    }

    private function registeredUser(UserType $type, string $email, string $hash): User
    {
        return User::register(
            UserId::fromString('11111111-1111-1111-1111-111111111111'),
            $email,
            $hash,
            new \DateTimeImmutable('2025-01-01T10:00:00+00:00'),
            $type,
        );
    }
}
