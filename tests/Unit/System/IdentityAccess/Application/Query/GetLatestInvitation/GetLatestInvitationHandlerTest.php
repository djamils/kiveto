<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Query\GetLatestInvitation;

use App\System\IdentityAccess\Application\Query\GetLatestInvitation\GetLatestInvitation;
use App\System\IdentityAccess\Application\Query\GetLatestInvitation\GetLatestInvitationHandler;
use App\System\IdentityAccess\Domain\Token\AccountToken;
use App\System\IdentityAccess\Domain\Token\AccountTokenId;
use App\System\IdentityAccess\Domain\Token\TokenPurpose;
use App\Tests\Shared\Time\FrozenClock;
use App\Tests\Unit\System\IdentityAccess\Support\InMemoryAccountTokens;
use PHPUnit\Framework\TestCase;

final class GetLatestInvitationHandlerTest extends TestCase
{
    private const string USER     = '01960000-0000-7000-8000-000000005c01';
    private const string CLINIC_A = '01960000-0000-7000-8000-000000005ca1';
    private const string CLINIC_B = '01960000-0000-7000-8000-000000005ca2';

    private InMemoryAccountTokens $tokens;

    protected function setUp(): void
    {
        $this->tokens = new InMemoryAccountTokens();
    }

    public function testNoInvitationNoAnswer(): void
    {
        $this->tokens->save(AccountToken::issue(AccountTokenId::fromString('r1'), self::USER, TokenPurpose::PASSWORD_RESET, null, 'h0', new \DateTimeImmutable('2026-11-05 10:00:00')));

        self::assertNull($this->ask('2026-11-06 10:00:00'));
    }

    public function testTheLatestUnusedLinkIsAnswered(): void
    {
        $this->tokens->save(AccountToken::issue(AccountTokenId::fromString('t1'), self::USER, TokenPurpose::INVITATION, self::CLINIC_A, 'h1', new \DateTimeImmutable('2026-10-20 10:00:00')));
        $this->tokens->save(AccountToken::issue(AccountTokenId::fromString('t2'), self::USER, TokenPurpose::INVITATION, self::CLINIC_B, 'h2', new \DateTimeImmutable('2026-11-01 10:00:00')));
        $used = AccountToken::issue(AccountTokenId::fromString('t3'), self::USER, TokenPurpose::INVITATION, self::CLINIC_A, 'h3', new \DateTimeImmutable('2026-11-03 10:00:00'));
        $used->markUsed(new \DateTimeImmutable('2026-11-03 11:00:00'));
        $this->tokens->save($used);

        $view = $this->ask('2026-11-06 10:00:00');

        self::assertNotNull($view);
        self::assertSame([self::CLINIC_B, '2026-11-01 10:00', '2026-11-08 10:00', false], [$view->clinicId, $view->sentAt->format('Y-m-d H:i'), $view->expiresAt->format('Y-m-d H:i'), $view->expired]);
    }

    public function testAnExpiredLinkSaysSo(): void
    {
        $this->tokens->save(AccountToken::issue(AccountTokenId::fromString('t1'), self::USER, TokenPurpose::INVITATION, self::CLINIC_A, 'h1', new \DateTimeImmutable('2026-10-20 10:00:00')));

        self::assertTrue($this->ask('2026-11-06 10:00:00')?->expired);
    }

    private function ask(string $now): ?\App\System\IdentityAccess\Application\Query\GetLatestInvitation\InvitationView
    {
        return (new GetLatestInvitationHandler($this->tokens, new FrozenClock(new \DateTimeImmutable($now))))(new GetLatestInvitation(self::USER));
    }
}
