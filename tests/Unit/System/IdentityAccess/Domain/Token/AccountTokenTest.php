<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\Token;

use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenId;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use PHPUnit\Framework\TestCase;

final class AccountTokenTest extends TestCase
{
    private const string USER   = '01960000-0000-7000-8000-0000000000a1';
    private const string CLINIC = '01960000-0000-7000-8000-0000000000c1';

    public function testASecretIsUrlSafeAndOnlyItsHashIsKept(): void
    {
        $secret = AccountToken::newSecret();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $secret);
        self::assertNotSame($secret, AccountToken::newSecret());

        $token = AccountToken::issue(AccountTokenId::fromString('t-1'), self::USER, TokenPurpose::PASSWORD_RESET, self::CLINIC, $secret, new \DateTimeImmutable('2026-10-26 10:00:00'));

        self::assertSame(hash('sha256', $secret), $token->tokenHash());
        self::assertSame(AccountToken::hash($secret), $token->tokenHash());
        self::assertNull($token->clinicId(), 'a reset belongs to no clinic');
        self::assertSame('2026-10-26 10:30:00', $token->expiresAt()->format('Y-m-d H:i:s'));
        self::assertSame(['t-1', self::USER, TokenPurpose::PASSWORD_RESET, '2026-10-26 10:00:00'], [$token->id()->toString(), $token->userId(), $token->purpose(), $token->createdAt()->format('Y-m-d H:i:s')]);
    }

    public function testAnInvitationLastsSeventyTwoHoursAndIsUsedOnce(): void
    {
        $token = AccountToken::issue(AccountTokenId::fromString('t-2'), self::USER, TokenPurpose::INVITATION, self::CLINIC, 'secret', new \DateTimeImmutable('2026-10-26 10:00:00'));

        self::assertSame(self::CLINIC, $token->clinicId());
        self::assertTrue($token->isUsable(new \DateTimeImmutable('2026-10-29 09:59:59')));
        self::assertFalse($token->isUsable(new \DateTimeImmutable('2026-10-29 10:00:00')), '73 h later');

        $token->markUsed(new \DateTimeImmutable('2026-10-27 08:00:00'));
        $token->markUsed(new \DateTimeImmutable('2026-10-27 09:00:00'));
        self::assertSame('2026-10-27 08:00:00', $token->usedAt()?->format('Y-m-d H:i:s'), 'the first use is kept');
        self::assertFalse($token->isUsable(new \DateTimeImmutable('2026-10-27 08:00:01')));
    }

    public function testAnInvitationNeedsAClinic(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AccountToken::issue(AccountTokenId::fromString('t-3'), self::USER, TokenPurpose::INVITATION, null, 'secret', new \DateTimeImmutable());
    }

    public function testReconstituteKeepsEverything(): void
    {
        $token = AccountToken::reconstitute(AccountTokenId::fromString('t-4'), self::USER, TokenPurpose::INVITATION, self::CLINIC, 'hash', new \DateTimeImmutable('2026-10-29'), new \DateTimeImmutable('2026-10-27'), new \DateTimeImmutable('2026-10-26'));

        self::assertSame(['hash', '2026-10-29', '2026-10-27', '2026-10-26'], [$token->tokenHash(), $token->expiresAt()->format('Y-m-d'), $token->usedAt()?->format('Y-m-d'), $token->createdAt()->format('Y-m-d')]);
    }
}
