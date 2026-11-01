<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange\ConfirmPortalEmailChange;
use App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange\ConfirmPortalEmailChangeHandler;
use App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange\EmailChangeResult;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\StartPortalEmailChange;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\StartPortalEmailChangeHandler;
use App\System\IdentityAccess\Application\Port\PortalEmailChangedNoticeRequest;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;

/** AC 61, AC 206, AC 207: the collision rule applies once the address is proven (D8). */
final class ConfirmPortalEmailChangeHandlerTest extends PortalActionOtpTestCase
{
    private const string CAROL       = '01960000-0000-7000-8000-0000000009e3';
    private const string CAROL_PHONE = '+33612340903';

    public function testTheProvenAddressBecomesTheVerifiedEmailAndTheOldOneIsTold(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'old@example.com', verified: true);
        $challengeId = $this->startChange(self::ALICE, 'new@example.com');

        self::assertSame(EmailChangeResult::CONFIRMED, $this->confirm(self::ALICE, $challengeId, '222222'));

        $alice = $this->users->users[self::ALICE];
        self::assertSame(['new@example.com', '13:07', true], [$alice->email(), $alice->emailVerifiedAt()?->format('H:i'), $alice->hasVerifiedEmail()]);
        self::assertContains(self::ALICE, $this->users->locked, 'the caller is locked');
        self::assertSame([[$challengeId, self::ALICE, 'old@example.com', 'n***@example.com', '01/11/2026 à 14:07']], array_map(
            static fn (PortalEmailChangedNoticeRequest $r): array => [$r->challengeId, $r->userId, $r->previousEmail, $r->maskedNewEmail, $r->changedAtLabel],
            $this->fakes->emailChangedNotices,
        ));

        self::assertSame(EmailChangeResult::OTP_INVALID, $this->confirm(self::ALICE, $challengeId, '222222'), 'used once');
    }

    public function testReVerifyingTheCurrentAddressSendsNoNotice(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'alice@example.com');
        $challengeId = $this->startChange(self::ALICE, 'alice@example.com');

        self::assertSame(EmailChangeResult::CONFIRMED, $this->confirm(self::ALICE, $challengeId, '222222'));
        self::assertTrue($this->users->users[self::ALICE]->hasVerifiedEmail());

        $again = $this->startChange(self::ALICE, 'ALICE@example.com');
        self::assertSame(EmailChangeResult::CONFIRMED, $this->confirm(self::ALICE, $again, '222222'));
        self::assertSame([], $this->fakes->emailChangedNotices, 'no unverified previous address, then the same address');
    }

    public function testAnUnverifiedHolderLosesTheAddress(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $this->owner(self::BOB, self::BOB_PHONE, 'shared@example.com');
        $challengeId = $this->startChange(self::ALICE, 'shared@example.com');

        self::assertSame(EmailChangeResult::CONFIRMED, $this->confirm(self::ALICE, $challengeId, '222222'));

        self::assertNull($this->users->users[self::BOB]->email());
        self::assertSame('shared@example.com', $this->users->users[self::ALICE]->email());
        self::assertSame([self::ALICE, self::BOB], $this->users->locked, 'the caller, then the holder of the address');
        self::assertSame([], $this->fakes->emailChangedNotices, 'Alice had no verified address');
    }

    public function testAVerifiedHolderKeepsTheAddress(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'alice@example.com', verified: true);
        $this->owner(self::CAROL, self::CAROL_PHONE, 'carol@example.com', verified: true);
        $challengeId = $this->startChange(self::ALICE, 'carol@example.com');

        self::assertSame(EmailChangeResult::EMAIL_TAKEN, $this->confirm(self::ALICE, $challengeId, '222222'));

        self::assertSame(['alice@example.com', 'carol@example.com'], [$this->users->users[self::ALICE]->email(), $this->users->users[self::CAROL]->email()]);
        self::assertSame([], $this->fakes->emailChangedNotices);
        self::assertSame(EmailChangeResult::OTP_INVALID, $this->confirm(self::ALICE, $challengeId, '222222'), 'the code is used');
    }

    public function testTheVerificationCodeIsBoundToItsUserAndLimited(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $this->owner(self::BOB, self::BOB_PHONE);
        $challengeId = $this->startChange(self::ALICE, 'alice@example.com');

        self::assertSame(EmailChangeResult::OTP_INVALID, $this->confirm(self::BOB, $challengeId, '222222'), 'the code of Alice for Bob');
        for ($attempt = 2; $attempt <= 4; ++$attempt) {
            self::assertSame(EmailChangeResult::OTP_INVALID, $this->confirm(self::ALICE, $challengeId, '000000'));
        }
        self::assertSame(EmailChangeResult::OTP_ATTEMPTS_EXCEEDED, $this->confirm(self::ALICE, $challengeId, '000000'));

        $expired = $this->startChange(self::ALICE, 'alice@example.com');
        self::assertSame(EmailChangeResult::OTP_EXPIRED, $this->confirm(self::ALICE, $expired, '222222', '+12 minutes'));
        self::assertNull($this->users->users[self::ALICE]->email());
    }

    public function testADisabledCallerVerifiesNothing(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $challengeId = $this->startChange(self::ALICE, 'alice@example.com');
        $this->owner(self::ALICE, self::ALICE_PHONE, status: UserStatus::DISABLED);

        $this->expectExceptionMessage('Ce compte n\'est plus disponible.');
        $this->confirm(self::ALICE, $challengeId, '222222');
    }

    /** Returns the e-mail verification challenge, whose code is 222222. */
    private function startChange(string $userId, string $email): string
    {
        $sms                       = $this->requestActionCode($userId);
        $this->portal->nextCodes[] = '222222';
        $handler                   = new StartPortalEmailChangeHandler($this->verifier(), $this->owners(), $this->challenges, $this->portal, $this->portal, $this->fakes, $this->fakes, $this->clock('+1 minute'));
        $started                   = $handler(new StartPortalEmailChange($userId, $sms->challengeId, '123456', $email));
        self::assertNotNull($started->verificationChallengeId);

        return $started->verificationChallengeId;
    }

    private function confirm(string $userId, string $challengeId, string $code, string $after = '+2 minutes'): EmailChangeResult
    {
        $handler = new ConfirmPortalEmailChangeHandler($this->verifier($after), $this->owners(), $this->users, $this->fakes, $this->clock($after));

        return $handler(new ConfirmPortalEmailChange($userId, $challengeId, $code));
    }
}
