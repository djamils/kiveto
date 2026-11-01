<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\IdentityAccess\Domain\Otp;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Exception\OtpChallengeNotFoundException;
use App\System\IdentityAccess\Domain\Exception\OtpRateLimitedException;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChannel;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpVerificationResult;
use PHPUnit\Framework\TestCase;

/**
 * D4: 10 minutes, 5 attempts, single use, resend after 30 s, purpose-bound;
 * D29: the onboarding grant of a login verification.
 */
final class OtpChallengeTest extends TestCase
{
    private const string ID         = '01960000-0000-7000-8000-0000000000f1';
    private const string INVITATION = '01960000-0000-7000-8000-0000000000b1';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-26 07:14:00', new \DateTimeZone('UTC'));
    }

    public function testAStartedChallengeLivesTenMinutesAndAllowsAResendAfterThirtySeconds(): void
    {
        $challenge = $this->login();

        self::assertSame([self::ID, '+33612345678', OtpPurpose::LOGIN, null, $this->digest('111111'), 0, 1], [
            $challenge->id()->toString(), $challenge->phone()->toString(), $challenge->purpose(), $challenge->subjectRef(), $challenge->codeHash(), $challenge->attempts(), $challenge->sendCount(),
        ]);
        self::assertSame(['07:24:00', '07:14:30', '07:14:00'], [$challenge->expiresAt()->format('H:i:s'), $challenge->resendAvailableAt()->format('H:i:s'), $challenge->createdAt()->format('H:i:s')]);
        self::assertSame([OtpChannel::SMS, false, null, null, null, null], [
            $challenge->lastChannel(), $challenge->isSmsSuppressed(), $challenge->consumedAt(), $challenge->onboardingTokenHash(), $challenge->onboardingExpiresAt(), $challenge->onboardingRevokedAt(),
        ]);
        self::assertFalse($challenge->isOnboardingUsable($this->now));
    }

    public function testAnInvitationChallengeIsBoundToItsInvitationAndALoginChallengeToNone(): void
    {
        $invitation = $this->invitation(smsSuppressed: true);
        self::assertSame([OtpPurpose::INVITATION, self::INVITATION, true], [$invitation->purpose(), $invitation->subjectRef(), $invitation->isSmsSuppressed()]);

        $expected = [
            [OtpPurpose::INVITATION, null, 'A code of purpose "invitation" is bound to its invitation.'],
            [OtpPurpose::LOGIN, self::INVITATION, 'A code of purpose "login" is bound to no subject.'],
        ];
        foreach ($expected as [$purpose, $ref, $message]) {
            try {
                OtpChallenge::start(OtpChallengeId::fromString(self::ID), PhoneNumber::fromString('+33612345678'), $purpose, $ref, $this->digest('111111'), $this->now);
                self::fail('The subject must match the purpose.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testTheRightCodeIsVerifiedOnceAndThenOnlyRecognisedAsAReplay(): void
    {
        $challenge = $this->login();

        self::assertSame(OtpVerificationResult::VERIFIED, $challenge->verify($this->digest('111111'), $this->at('+1 minute'), OtpPurpose::LOGIN));
        self::assertSame('07:15:00', $challenge->consumedAt()?->format('H:i:s'));

        self::assertSame(OtpVerificationResult::ALREADY_VERIFIED, $challenge->verify($this->digest('111111'), $this->at('+9 minutes'), OtpPurpose::LOGIN));
        self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('111111'), $this->at('+10 minutes'), OtpPurpose::LOGIN), 'no replay past the original expiry');
        self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('222222'), $this->at('+2 minutes'), OtpPurpose::LOGIN), 'a wrong code on a used challenge');
        self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('111111'), $this->at('+2 minutes'), OtpPurpose::INVITATION, self::INVITATION), 'a replay for another purpose');
        self::assertSame(3, $challenge->attempts(), 'every wrong guess on a used challenge counts');

        $challenge->verify($this->digest('222222'), $this->at('+3 minutes'), OtpPurpose::LOGIN);
        $challenge->verify($this->digest('222222'), $this->at('+3 minutes'), OtpPurpose::LOGIN);
        self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('111111'), $this->at('+3 minutes'), OtpPurpose::LOGIN), 'no replay once the attempts are spent');
        self::assertSame('07:15:00', $challenge->consumedAt()->format('H:i:s'));
    }

    public function testAnInvitationReplayMustNameTheSameInvitation(): void
    {
        $challenge = $this->invitation();

        self::assertSame(OtpVerificationResult::VERIFIED, $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::INVITATION, self::INVITATION));
        self::assertSame(OtpVerificationResult::ALREADY_VERIFIED, $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::INVITATION, self::INVITATION));
        self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::INVITATION, '01960000-0000-7000-8000-0000000000b2'));
    }

    public function testACodeIsExpiredAfterTenMinutesWithoutCountingAnAttempt(): void
    {
        $challenge = $this->login();

        self::assertSame(OtpVerificationResult::EXPIRED, $challenge->verify($this->digest('111111'), $this->at('+10 minutes'), OtpPurpose::LOGIN));
        self::assertSame(OtpVerificationResult::EXPIRED, $challenge->verify($this->digest('000000'), $this->at('+11 minutes'), OtpPurpose::LOGIN));
        self::assertSame([0, null], [$challenge->attempts(), $challenge->consumedAt()]);
    }

    public function testTheFifthWrongCodeExhaustsTheChallengeEvenForTheRightCode(): void
    {
        $challenge = $this->login();

        for ($i = 1; $i <= 4; ++$i) {
            self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('000000'), $this->now, OtpPurpose::LOGIN));
            self::assertSame($i, $challenge->attempts());
        }

        self::assertSame(OtpVerificationResult::ATTEMPTS_EXCEEDED, $challenge->verify($this->digest('000000'), $this->now, OtpPurpose::LOGIN));
        self::assertSame(OtpVerificationResult::ATTEMPTS_EXCEEDED, $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::LOGIN));
        self::assertSame([OtpChallenge::MAX_ATTEMPTS, null], [$challenge->attempts(), $challenge->consumedAt()]);
    }

    /**
     * A code presented on the routes of the other purpose (or for another
     * invitation) is a wrong attempt: it is counted, but answers INVALID
     * rather than revealing the state of a challenge the caller does not own.
     */
    public function testAPurposeOrInvitationMismatchCountsAnAttempt(): void
    {
        $challenge = $this->invitation();

        self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::LOGIN));
        self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::INVITATION, '01960000-0000-7000-8000-0000000000b2'));
        self::assertSame(2, $challenge->attempts());

        for ($i = 0; $i < 3; ++$i) {
            $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::LOGIN);
        }

        self::assertSame(5, $challenge->attempts());
        self::assertSame(OtpVerificationResult::ATTEMPTS_EXCEEDED, $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::INVITATION, self::INVITATION));
    }

    public function testAResendReplacesTheCodeWithFreshAttemptsAndValidity(): void
    {
        $challenge = $this->login();
        $challenge->verify($this->digest('000000'), $this->now, OtpPurpose::LOGIN);

        try {
            $challenge->resend($this->digest('222222'), OtpChannel::SMS, $this->at('+29 seconds'));
            self::fail('A resend before 30 s is rate limited.');
        } catch (OtpRateLimitedException $exception) {
            self::assertSame('07:14:30', $exception->availableAt->format('H:i:s'));
        }

        $challenge->resend($this->digest('222222'), OtpChannel::SMS, $this->at('+30 seconds'));

        self::assertSame([$this->digest('222222'), 0, 2, OtpChannel::SMS], [$challenge->codeHash(), $challenge->attempts(), $challenge->sendCount(), $challenge->lastChannel()]);
        self::assertSame(['07:24:30', '07:15:00'], [$challenge->expiresAt()->format('H:i:s'), $challenge->resendAvailableAt()->format('H:i:s')]);
        self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('111111'), $this->at('+1 minute'), OtpPurpose::LOGIN), 'the previous code stops working');
        self::assertSame(OtpVerificationResult::VERIFIED, $challenge->verify($this->digest('222222'), $this->at('+1 minute'), OtpPurpose::LOGIN));
    }

    public function testAUsedChallengeCannotBeResent(): void
    {
        $challenge = $this->login();
        $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::LOGIN);

        $this->expectException(OtpChallengeNotFoundException::class);
        $this->expectExceptionMessage('Ce code de connexion n\'existe pas ou a déjà été utilisé.');

        $challenge->resend($this->digest('222222'), OtpChannel::SMS, $this->at('+1 minute'));
    }

    public function testTheOnboardingGrantIsMintedOnceByTheLoginVerificationThatConsumedTheChallenge(): void
    {
        $challenge = $this->login();
        $this->assertGrantRefused($challenge, 'not verified yet');

        $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::LOGIN);
        $challenge->grantOnboarding(hash('sha256', 'grant'), $this->now);

        self::assertSame([hash('sha256', 'grant'), '07:29:00'], [$challenge->onboardingTokenHash(), $challenge->onboardingExpiresAt()?->format('H:i:s')]);
        self::assertTrue($challenge->isOnboardingUsable($this->at('+14 minutes')));
        self::assertFalse($challenge->isOnboardingUsable($this->at('+15 minutes')));
        $this->assertGrantRefused($challenge, 'a second grant');
    }

    public function testAReloadedOrInvitationChallengeNeverGetsAGrant(): void
    {
        $invitation = $this->invitation();
        $invitation->verify($this->digest('111111'), $this->now, OtpPurpose::INVITATION, self::INVITATION);
        $this->assertGrantRefused($invitation, 'an invitation challenge');

        $login = $this->login();
        $login->verify($this->digest('111111'), $this->now, OtpPurpose::LOGIN);
        $reloaded = $this->reload($login);
        $this->assertGrantRefused($reloaded, 'only the verification that consumed it in this request may grant');
        self::assertSame(OtpVerificationResult::ALREADY_VERIFIED, $reloaded->verify($this->digest('111111'), $this->now, OtpPurpose::LOGIN));
        $this->assertGrantRefused($reloaded, 'a replay does not mint a grant either');
    }

    public function testARevokedGrantIsNoLongerUsableAndKeepsItsFirstRevocationDate(): void
    {
        $challenge = $this->login();
        $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::LOGIN);
        $challenge->grantOnboarding(hash('sha256', 'grant'), $this->now);

        $challenge->revokeOnboarding($this->at('+1 minute'));
        $challenge->revokeOnboarding($this->at('+2 minutes'));

        self::assertSame('07:15:00', $challenge->onboardingRevokedAt()?->format('H:i:s'));
        self::assertFalse($challenge->isOnboardingUsable($this->at('+3 minutes')));
    }

    public function testAGrantWithoutExpiryIsNotUsable(): void
    {
        $challenge = OtpChallenge::reconstitute(
            OtpChallengeId::fromString(self::ID),
            PhoneNumber::fromString('+33612345678'),
            OtpPurpose::LOGIN,
            null,
            $this->digest('111111'),
            0,
            1,
            $this->at('+10 minutes'),
            $this->at('+30 seconds'),
            OtpChannel::SMS,
            false,
            $this->now,
            hash('sha256', 'grant'),
            null,
            null,
            $this->now,
        );

        self::assertFalse($challenge->isOnboardingUsable($this->now));
    }

    private function assertGrantRefused(OtpChallenge $challenge, string $message): void
    {
        try {
            $challenge->grantOnboarding(hash('sha256', 'other'), $this->now);
            self::fail('Grant should be refused: ' . $message);
        } catch (\LogicException $exception) {
            self::assertSame('An onboarding grant is minted once, by the verification of a login code.', $exception->getMessage(), $message);
        }
    }

    private function reload(OtpChallenge $c): OtpChallenge
    {
        return OtpChallenge::reconstitute(
            $c->id(),
            $c->phone(),
            $c->purpose(),
            $c->subjectRef(),
            $c->codeHash(),
            $c->attempts(),
            $c->sendCount(),
            $c->expiresAt(),
            $c->resendAvailableAt(),
            $c->lastChannel(),
            $c->isSmsSuppressed(),
            $c->consumedAt(),
            $c->onboardingTokenHash(),
            $c->onboardingExpiresAt(),
            $c->onboardingRevokedAt(),
            $c->createdAt(),
        );
    }

    private function login(): OtpChallenge
    {
        return OtpChallenge::start(OtpChallengeId::fromString(self::ID), PhoneNumber::fromString('+33612345678'), OtpPurpose::LOGIN, null, $this->digest('111111'), $this->now);
    }

    private function invitation(bool $smsSuppressed = false): OtpChallenge
    {
        return OtpChallenge::start(OtpChallengeId::fromString(self::ID), PhoneNumber::fromString('+33612345678'), OtpPurpose::INVITATION, self::INVITATION, $this->digest('111111'), $this->now, $smsSuppressed);
    }

    private function digest(string $code): string
    {
        return hash('sha256', 'otp:' . $code);
    }

    private function at(string $modifier): \DateTimeImmutable
    {
        return $this->now->modify($modifier);
    }
}
