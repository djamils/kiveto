<?php

declare(strict_types=1);

namespace App\Tests\Unit\IdentityAccess\Application\Query\AuthenticateUser;

use App\IdentityAccess\Application\Query\AuthenticateUser\AuthenticationContext;
use App\IdentityAccess\Domain\ValueObject\UserType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthenticationContextTest extends TestCase
{
    #[DataProvider('provideAllowedUserTypeCases')]
    public function testAllowedUserType(AuthenticationContext $context, UserType $expected): void
    {
        self::assertSame($expected, $context->allowedUserType());
    }

    /**
     * @return iterable<string, array{AuthenticationContext, UserType}>
     */
    public static function provideAllowedUserTypeCases(): iterable
    {
        return [
            'clinic'     => [AuthenticationContext::CLINIC, UserType::CLINIC],
            'portal'     => [AuthenticationContext::PORTAL, UserType::PORTAL],
            'backoffice' => [AuthenticationContext::BACKOFFICE, UserType::BACKOFFICE],
        ];
    }
}
