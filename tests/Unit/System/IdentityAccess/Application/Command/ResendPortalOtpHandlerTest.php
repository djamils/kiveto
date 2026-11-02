<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Application\Command;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\ResendPortalOtp\ResendPortalOtp;
use App\System\IdentityAccess\Application\Command\ResendPortalOtp\ResendPortalOtpHandler;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\StartPortalEmailChange;
use App\System\IdentityAccess\Application\Command\StartPortalEmailChange\StartPortalEmailChangeHandler;
use App\System\IdentityAccess\Application\Port\PortalOtpRequest;
use App\System\IdentityAccess\Application\Service\UnknownNumberSmsGate;
use App\System\IdentityAccess\Domain\Exception\OtpRateLimitedException;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChannel;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpVerificationResult;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;

/**
 * AC 62, AC 208: a verified e-mail is a second sign-in factor (D6); an
 * action code is never sent by e-mail, and a resend that sends nothing
 * answers like one that does.
 */
final class ResendPortalOtpHandlerTest extends PortalActionOtpTestCase
{
    private const string LOGIN = '01960000-0000-7000-8000-0000000009c1';

    public function testASignInCodeIsResentToTheVerifiedEmail(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'alice@example.com', verified: true);
        $this->login(self::ALICE_PHONE);
        $this->portal->nextCodes[] = '424242';

        $issued = $this->resend(self::LOGIN, 'email');

        $challenge = $this->challenges->single();
        self::assertSame([OtpChannel::EMAIL, 2, '13:15:35', '13:06:05'], [$challenge->lastChannel(), $challenge->sendCount(), $issued->expiresAt->format('H:i:s'), $issued->resendAvailableAt->format('H:i:s')]);
        self::assertSame([[self::LOGIN, '424242', 2, 'login', 'alice@example.com']], array_map(
            static fn (array $sent): array => [$sent[0]->challengeId, $sent[0]->code, $sent[0]->sendCount, $sent[0]->purpose, $sent[1]],
            $this->fakes->emailedOtps,
        ));
        self::assertSame([], $this->fakes->portalOtps, 'no SMS');
        self::assertSame(OtpVerificationResult::VERIFIED, $challenge->verify($this->portal->hash('424242'), $this->now->modify('+1 minute'), OtpPurpose::LOGIN));
    }

    public function testWithoutAVerifiedEmailNothingChangesAndTheSmsCodeStaysValid(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'unverified@example.com');
        $this->owner(self::BOB, self::BOB_PHONE, 'bob@example.com', verified: true, status: UserStatus::DISABLED);

        foreach ([self::ALICE_PHONE, self::BOB_PHONE, '+33612340999'] as $phone) {
            $this->challenges->challenges = [];
            $this->login($phone);

            $issued = $this->resend(self::LOGIN, 'email');

            $challenge = $this->challenges->single();
            self::assertSame([self::LOGIN, '13:15:35', '13:06:05'], [$issued->challengeId, $issued->expiresAt->format('H:i:s'), $issued->resendAvailableAt->format('H:i:s')], 'it looks like a resend');
            self::assertSame([1, OtpChannel::SMS, '13:15:00'], [$challenge->sendCount(), $challenge->lastChannel(), $challenge->expiresAt()->format('H:i:s')], 'untouched');
            self::assertSame(OtpVerificationResult::VERIFIED, $challenge->verify($this->portal->hash('123456'), $this->now->modify('+1 minute'), OtpPurpose::LOGIN), 'the SMS code still verifies');
        }

        self::assertSame([[], []], [$this->fakes->emailedOtps, $this->fakes->portalOtps]);
    }

    public function testAnActionCodeIsNeverSentByEmailButResentBySmsWithItsWording(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE, 'alice@example.com', verified: true);

        foreach (['email_change', 'account_deletion'] as $purpose) {
            $this->challenges->challenges = [];
            $this->fakes->portalOtps      = [];
            $issued                       = $this->requestActionCode(self::ALICE, $purpose);

            $this->resend($issued->challengeId, 'email');
            self::assertSame([1, []], [$this->challenges->single()->sendCount(), $this->fakes->emailedOtps], $purpose);

            $this->portal->nextCodes[] = '515151';
            $this->resend($issued->challengeId, 'sms');
            self::assertSame([2, OtpChannel::SMS], [$this->challenges->single()->sendCount(), $this->challenges->single()->lastChannel()]);
            self::assertSame([[self::ALICE_PHONE, '515151', 2, $purpose]], array_map(
                static fn (PortalOtpRequest $r): array => [$r->phone, $r->code, $r->sendCount, $r->purpose],
                \array_slice($this->fakes->portalOtps, 1),
            ));
        }
    }

    public function testAnEmailVerificationCodeIsNeverResent(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $sms                       = $this->requestActionCode(self::ALICE);
        $this->portal->nextCodes[] = '222222';
        $started                   = (new StartPortalEmailChangeHandler($this->verifier(), $this->owners(), $this->challenges, $this->portal, $this->portal, $this->fakes, $this->fakes, $this->clock()))(
            new StartPortalEmailChange(self::ALICE, $sms->challengeId, '123456', 'new@example.com'),
        );
        self::assertNotNull($started->verificationChallengeId);

        foreach (['sms', 'email'] as $channel) {
            $this->resend($started->verificationChallengeId, $channel);
        }

        $challenge = $this->challenges->findById(OtpChallengeId::fromString($started->verificationChallengeId));
        self::assertSame([1, OtpChannel::EMAIL], [$challenge?->sendCount(), $challenge?->lastChannel()]);
        self::assertSame([1, 1, []], [\count($this->fakes->portalOtps), \count($this->fakes->emailVerifications), $this->fakes->emailedOtps], 'the code reaches the address only, once');
    }

    public function testANoOpResendKeepsTheResendDelay(): void
    {
        $this->owner(self::ALICE, self::ALICE_PHONE);
        $this->login(self::ALICE_PHONE);

        $this->expectException(OtpRateLimitedException::class);
        $this->resend(self::LOGIN, 'email', '+10 seconds');
    }

    public function testASuppressedSignInCodeIsNeverSent(): void
    {
        $this->challenges->save(OtpChallenge::start(OtpChallengeId::fromString(self::LOGIN), PhoneNumber::fromString('+33612340998'), OtpPurpose::LOGIN, null, $this->portal->hash('123456'), $this->now, true));

        $this->resend(self::LOGIN, 'sms');

        self::assertSame([2, [], []], [$this->challenges->single()->sendCount(), $this->fakes->portalOtps, $this->fakes->emailedOtps]);
    }

    private function login(string $phone): void
    {
        $this->challenges->save(OtpChallenge::start(OtpChallengeId::fromString(self::LOGIN), PhoneNumber::fromString($phone), OtpPurpose::LOGIN, null, $this->portal->hash('123456'), $this->now));
    }

    private function resend(string $challengeId, string $channel, string $after = '+35 seconds'): OtpChallengeIssued
    {
        $handler = new ResendPortalOtpHandler($this->challenges, $this->users, $this->portal, $this->portal, $this->fakes, $this->clock($after), new UnknownNumberSmsGate($this->users, $this->portal, $this->portal));

        return $handler(new ResendPortalOtp($challengeId, $channel));
    }
}
