<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\CheckAccountToken;

use App\System\IdentityAccess\Application\Query\CheckAccountToken\AccountTokenCheck;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\CheckAccountToken;
use App\System\IdentityAccess\Application\Query\CheckAccountToken\CheckAccountTokenHandler;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenId;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryAccountTokens;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryUsers;
use PHPUnit\Framework\TestCase;

final class CheckAccountTokenHandlerTest extends TestCase
{
    private const string PENDING  = '01960000-0000-7000-8000-0000000000a1';
    private const string ACTIVE   = '01960000-0000-7000-8000-0000000000a2';
    private const string DISABLED = '01960000-0000-7000-8000-0000000000a3';
    private const string GONE     = '01960000-0000-7000-8000-0000000000a4';

    public function testWhatALinkStillAllows(): void
    {
        $users = new InMemoryUsers();
        $users->save($this->user(self::PENDING, UserStatus::PENDING));
        $users->save($this->user(self::ACTIVE, UserStatus::ACTIVE));
        $users->save($this->user(self::DISABLED, UserStatus::DISABLED));

        $tokens = new InMemoryAccountTokens();
        $issued = new \DateTimeImmutable('2026-10-26 10:00:00');
        $links  = [
            ['inv-pending', self::PENDING, TokenPurpose::INVITATION],
            ['inv-active', self::ACTIVE, TokenPurpose::INVITATION],
            ['inv-disabled', self::DISABLED, TokenPurpose::INVITATION],
            ['inv-gone', self::GONE, TokenPurpose::INVITATION],
            ['reset-active', self::ACTIVE, TokenPurpose::PASSWORD_RESET],
            ['reset-pending', self::PENDING, TokenPurpose::PASSWORD_RESET],
        ];

        foreach ($links as $i => [$secret, $userId, $purpose]) {
            $tokens->save(AccountToken::issue(AccountTokenId::fromString('t-' . $i), $userId, $purpose, 'clinic', $secret, $issued));
        }

        $handler = new CheckAccountTokenHandler($tokens, $users, new FrozenClock(new \DateTimeImmutable('2026-10-26 10:10:00')));
        $check   = static fn (string $secret, string $purpose): array => [($c = $handler(new CheckAccountToken($secret, $purpose)))->state, $c->email, $c->isValid()];

        self::assertSame(['VALID', 'a1@example.com', true], $check('inv-pending', CheckAccountToken::INVITATION));
        self::assertSame(['ALREADY_ACTIVE', null, false], $check('inv-active', CheckAccountToken::INVITATION));
        self::assertSame(['INVALID', null, false], $check('inv-disabled', CheckAccountToken::INVITATION));
        self::assertSame(['INVALID', null, false], $check('inv-gone', CheckAccountToken::INVITATION));
        self::assertSame(['VALID', 'a2@example.com', true], $check('reset-active', CheckAccountToken::PASSWORD_RESET));
        self::assertSame(['INVALID', null, false], $check('reset-pending', CheckAccountToken::PASSWORD_RESET));
        self::assertSame(['INVALID', null, false], $check('inv-pending', CheckAccountToken::PASSWORD_RESET), 'a link of another kind');
        self::assertSame(['INVALID', null, false], $check('unknown', CheckAccountToken::INVITATION));

        $late = new CheckAccountTokenHandler($tokens, $users, new FrozenClock(new \DateTimeImmutable('2026-10-26 10:31:00')));
        self::assertSame(AccountTokenCheck::INVALID, $late(new CheckAccountToken('reset-active', CheckAccountToken::PASSWORD_RESET))->state, 'expired after 30 minutes');
    }

    private function user(string $id, UserStatus $status): User
    {
        return User::reconstitute(UserId::fromString($id), substr($id, -2) . '@example.com', '$h', new \DateTimeImmutable(), $status, null, UserType::CLINIC);
    }
}
