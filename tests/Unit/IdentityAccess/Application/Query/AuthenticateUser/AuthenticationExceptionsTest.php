<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Query\AuthenticateUser;

use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\AccountStatusNotAllowedException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\AuthenticationContextMismatchException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\EmailVerificationRequiredException;
use App\IdentityAccess\Application\Query\AuthenticateUser\Exception\InvalidCredentialsException;
use App\IdentityAccess\Domain\ValueObject\UserStatus;
use App\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\TestCase;

final class AuthenticationExceptionsTest extends TestCase
{
    public function testInvalidCredentials(): void
    {
        $ex = new InvalidCredentialsException();

        self::assertSame(401, $ex->httpStatusCode());
        self::assertSame('INVALID_CREDENTIALS', $ex->errorCode());
    }

    public function testAccountStatusNotAllowed(): void
    {
        $ex = new AccountStatusNotAllowedException(UserStatus::DISABLED);

        self::assertSame(403, $ex->httpStatusCode());
        self::assertSame('ACCOUNT_STATUS_NOT_ALLOWED', $ex->errorCode());
    }

    public function testAuthenticationContextMismatch(): void
    {
        $ex = new AuthenticationContextMismatchException(AuthenticationContext::CLINIC, UserType::PORTAL);

        self::assertSame(403, $ex->httpStatusCode());
        self::assertSame('CONTEXT_MISMATCH', $ex->errorCode());
    }

    public function testEmailVerificationRequired(): void
    {
        $ex = new EmailVerificationRequiredException();

        self::assertSame(403, $ex->httpStatusCode());
        self::assertSame('EMAIL_VERIFICATION_REQUIRED', $ex->errorCode());
    }
}
