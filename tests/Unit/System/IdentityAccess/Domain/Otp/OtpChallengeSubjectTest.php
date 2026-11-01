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
use App\System\IdentityAccess\Domain\Otp\OtpSubjectType;
use App\System\IdentityAccess\Domain\Otp\OtpVerificationResult;
use PHPUnit\Framework\TestCase;

/**
 * The purpose fixes the subject a challenge is bound to (S5e): an action
 * code of user A never confirms anything for user B.
 */
final class OtpChallengeSubjectTest extends TestCase
{
    private const string ID     = '01960000-0000-7000-8000-0000000007a1';
    private const string USER_A = '01960000-0000-7000-8000-0000000007e1';
    private const string USER_B = '01960000-0000-7000-8000-0000000007e2';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-01 10:00:00', new \DateTimeZone('UTC'));
    }

    public function testEachPurposeHasItsSubject(): void
    {
        self::assertSame(
            ['login' => null, 'invitation' => OtpSubjectType::INVITATION, 'email_change' => OtpSubjectType::USER, 'account_deletion' => OtpSubjectType::USER, 'email_verification' => OtpSubjectType::USER],
            array_combine(array_map(static fn (OtpPurpose $p): string => $p->value, OtpPurpose::cases()), array_map(static fn (OtpPurpose $p): ?OtpSubjectType => $p->subjectType(), OtpPurpose::cases())),
        );
        self::assertSame([OtpPurpose::EMAIL_CHANGE, OtpPurpose::ACCOUNT_DELETION], array_values(array_filter(OtpPurpose::cases(), static fn (OtpPurpose $p): bool => $p->isAction())));
    }

    public function testAnActionCodeIsBoundToItsUser(): void
    {
        $challenge = $this->start(OtpPurpose::ACCOUNT_DELETION, self::USER_A);
        self::assertSame([OtpSubjectType::USER, self::USER_A, null, OtpChannel::SMS], [$challenge->subjectType(), $challenge->subjectRef(), $challenge->targetEmail(), $challenge->lastChannel()]);

        try {
            $this->start(OtpPurpose::EMAIL_CHANGE, null);
            self::fail('An action code needs its user.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('A code of purpose "email_change" is bound to its user.', $exception->getMessage());
        }
    }

    public function testTheCodeOfUserANeverConfirmsAnActionOfUserB(): void
    {
        $challenge = $this->start(OtpPurpose::EMAIL_CHANGE, self::USER_A);

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::assertSame(OtpVerificationResult::INVALID, $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::EMAIL_CHANGE, self::USER_B), 'never « attempts exceeded » for another user');
        }
        self::assertSame(OtpVerificationResult::ATTEMPTS_EXCEEDED, $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::EMAIL_CHANGE, self::USER_A), 'the wrong subject spent the attempts');

        $other = $this->start(OtpPurpose::EMAIL_CHANGE, self::USER_A);
        self::assertSame(OtpVerificationResult::INVALID, $other->verify($this->digest('111111'), $this->now, OtpPurpose::ACCOUNT_DELETION, self::USER_A), 'another action');
        self::assertSame(OtpVerificationResult::VERIFIED, $other->verify($this->digest('111111'), $this->now, OtpPurpose::EMAIL_CHANGE, self::USER_A));
    }

    public function testAnEmailVerificationCodeCarriesTheAddressItProves(): void
    {
        $challenge = $this->start(OtpPurpose::EMAIL_VERIFICATION, self::USER_A, 'new@example.com');
        self::assertSame(['new@example.com', OtpChannel::EMAIL, OtpSubjectType::USER], [$challenge->targetEmail(), $challenge->lastChannel(), $challenge->subjectType()]);

        foreach ([[OtpPurpose::EMAIL_VERIFICATION, null], [OtpPurpose::EMAIL_CHANGE, 'new@example.com']] as [$purpose, $email]) {
            try {
                $this->start($purpose, self::USER_A, $email);
                self::fail('The address goes with the e-mail verification only.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('An e-mail verification code, and only it, carries the address it proves.', $exception->getMessage());
            }
        }

        $restored = OtpChallenge::reconstitute(
            $challenge->id(),
            $challenge->phone(),
            $challenge->purpose(),
            $challenge->subjectRef(),
            $challenge->codeHash(),
            0,
            1,
            $challenge->expiresAt(),
            $challenge->resendAvailableAt(),
            $challenge->lastChannel(),
            false,
            null,
            null,
            null,
            null,
            $challenge->createdAt(),
            $challenge->targetEmail(),
        );
        self::assertSame(['new@example.com', self::USER_A], [$restored->targetEmail(), $restored->subjectRef()]);
    }

    public function testAResendCheckCanRunWithoutSendingAnything(): void
    {
        $challenge = $this->start(OtpPurpose::EMAIL_CHANGE, self::USER_A);

        try {
            $challenge->assertResendable($this->now->modify('+10 seconds'));
            self::fail('Too early.');
        } catch (OtpRateLimitedException $exception) {
            self::assertSame('10:00:30', $exception->availableAt->format('H:i:s'));
        }

        $challenge->assertResendable($this->now->modify('+30 seconds'));
        self::assertSame(1, $challenge->sendCount(), 'nothing changed');

        $challenge->verify($this->digest('111111'), $this->now, OtpPurpose::EMAIL_CHANGE, self::USER_A);
        $this->expectException(OtpChallengeNotFoundException::class);
        $challenge->assertResendable($this->now->modify('+1 minute'));
    }

    private function start(OtpPurpose $purpose, ?string $subject, ?string $email = null): OtpChallenge
    {
        return OtpChallenge::start(OtpChallengeId::fromString(self::ID), PhoneNumber::fromString('+33612345678'), $purpose, $subject, $this->digest('111111'), $this->now, targetEmail: $email);
    }

    private function digest(string $code): string
    {
        return hash('sha256', $code);
    }
}
