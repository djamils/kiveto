<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Exception\OtpChallengeNotFoundException;
use App\System\IdentityAccess\Domain\Exception\OtpRateLimitedException;

/**
 * A one-time code sent to a mobile phone (or, to verify an address, to an
 * e-mail). Only an HMAC of the code is kept (a plain digest of 10⁶ values is
 * reversible). The code lives 10 minutes, allows 5 attempts and is used once;
 * a new one may be sent after 30 s.
 *
 * The purpose fixes the subject the challenge is bound to: an invitation
 * code to its invitation, an action code (`email_change`,
 * `account_deletion`, `email_verification`) to its user, a login code to
 * nothing. A code verified against another subject is a wrong attempt: the
 * code of user A never confirms an action of user B. An `email_verification`
 * challenge carries the address it proves.
 *
 * A login challenge verified for a phone that has pending invitations may
 * carry an onboarding grant: a 15-minute token that accepts those
 * invitations without a second code. It can only be minted in the very
 * verification that consumes the challenge, once.
 */
final class OtpChallenge
{
    public const int MAX_ATTEMPTS = 5;

    public const string LIFETIME     = 'PT10M';
    public const string RESEND_DELAY = 'PT30S';

    private const string ONBOARDING_LIFETIME = 'PT15M';

    private OtpChallengeId $id;
    private PhoneNumber $phone;
    private OtpPurpose $purpose;
    private ?string $subjectRef;
    private ?string $targetEmail;
    private string $codeHash;
    private int $attempts;
    private int $sendCount;
    private \DateTimeImmutable $expiresAt;
    private \DateTimeImmutable $resendAvailableAt;
    private OtpChannel $lastChannel;
    private bool $smsSuppressed;
    private ?\DateTimeImmutable $consumedAt;
    private ?string $onboardingTokenHash;
    private ?\DateTimeImmutable $onboardingExpiresAt;
    private ?\DateTimeImmutable $onboardingRevokedAt;
    private \DateTimeImmutable $createdAt;

    /** Set by the verification that consumed the challenge, never persisted. */
    private bool $consumedNow = false;

    private function __construct()
    {
    }

    /**
     * @param ?string $subjectRef    the invitation id or the user id the
     *                               purpose requires; null for a login code
     * @param bool    $smsSuppressed the code is not sent (unknown-number SMS
     *                               budget exhausted) and never will be, even
     *                               on a resend
     * @param ?string $targetEmail   the address an `email_verification`
     *                               code proves, and only it
     */
    public static function start(
        OtpChallengeId $id,
        PhoneNumber $phone,
        OtpPurpose $purpose,
        ?string $subjectRef,
        string $codeHash,
        \DateTimeImmutable $now,
        bool $smsSuppressed = false,
        ?string $targetEmail = null,
    ): self {
        if ((null === $purpose->subjectType()) !== (null === $subjectRef)) {
            throw new \InvalidArgumentException(\sprintf('A code of purpose "%s" is bound to %s.', $purpose->value, null === $purpose->subjectType() ? 'no subject' : 'its ' . $purpose->subjectType()->value));
        }

        if ((OtpPurpose::EMAIL_VERIFICATION === $purpose) === (null === $targetEmail)) {
            throw new \InvalidArgumentException('An e-mail verification code, and only it, carries the address it proves.');
        }

        $challenge                      = new self();
        $challenge->id                  = $id;
        $challenge->phone               = $phone;
        $challenge->purpose             = $purpose;
        $challenge->subjectRef          = $subjectRef;
        $challenge->targetEmail         = $targetEmail;
        $challenge->codeHash            = $codeHash;
        $challenge->attempts            = 0;
        $challenge->sendCount           = 1;
        $challenge->expiresAt           = $now->add(new \DateInterval(self::LIFETIME));
        $challenge->resendAvailableAt   = $now->add(new \DateInterval(self::RESEND_DELAY));
        $challenge->lastChannel         = null === $targetEmail ? OtpChannel::SMS : OtpChannel::EMAIL;
        $challenge->smsSuppressed       = $smsSuppressed;
        $challenge->consumedAt          = null;
        $challenge->onboardingTokenHash = null;
        $challenge->onboardingExpiresAt = null;
        $challenge->onboardingRevokedAt = null;
        $challenge->createdAt           = $now;

        return $challenge;
    }

    public static function reconstitute(
        OtpChallengeId $id,
        PhoneNumber $phone,
        OtpPurpose $purpose,
        ?string $subjectRef,
        string $codeHash,
        int $attempts,
        int $sendCount,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $resendAvailableAt,
        OtpChannel $lastChannel,
        bool $smsSuppressed,
        ?\DateTimeImmutable $consumedAt,
        ?string $onboardingTokenHash,
        ?\DateTimeImmutable $onboardingExpiresAt,
        ?\DateTimeImmutable $onboardingRevokedAt,
        \DateTimeImmutable $createdAt,
        ?string $targetEmail = null,
    ): self {
        $challenge                      = new self();
        $challenge->id                  = $id;
        $challenge->phone               = $phone;
        $challenge->purpose             = $purpose;
        $challenge->subjectRef          = $subjectRef;
        $challenge->targetEmail         = $targetEmail;
        $challenge->codeHash            = $codeHash;
        $challenge->attempts            = $attempts;
        $challenge->sendCount           = $sendCount;
        $challenge->expiresAt           = $expiresAt;
        $challenge->resendAvailableAt   = $resendAvailableAt;
        $challenge->lastChannel         = $lastChannel;
        $challenge->smsSuppressed       = $smsSuppressed;
        $challenge->consumedAt          = $consumedAt;
        $challenge->onboardingTokenHash = $onboardingTokenHash;
        $challenge->onboardingExpiresAt = $onboardingExpiresAt;
        $challenge->onboardingRevokedAt = $onboardingRevokedAt;
        $challenge->createdAt           = $createdAt;

        return $challenge;
    }

    /**
     * Never throws: every wrong attempt is counted and must be committed.
     * A code of another purpose or subject (invitation, user) counts as a
     * wrong attempt; the subject type follows from the purpose, so comparing
     * the purposes compares the subject types.
     */
    public function verify(
        string $codeHash,
        \DateTimeImmutable $now,
        OtpPurpose $expectedPurpose,
        ?string $expectedSubjectRef = null,
    ): OtpVerificationResult {
        $sameTarget = $expectedPurpose === $this->purpose && $expectedSubjectRef === $this->subjectRef;

        if (null !== $this->consumedAt) {
            // A used code stays guarded: its replay is honoured within the
            // validity and the attempt cap, and wrong guesses still count.
            if ($this->attempts >= self::MAX_ATTEMPTS) {
                return OtpVerificationResult::INVALID;
            }

            if ($sameTarget && $now < $this->expiresAt && hash_equals($this->codeHash, $codeHash)) {
                return OtpVerificationResult::ALREADY_VERIFIED;
            }

            ++$this->attempts;

            return OtpVerificationResult::INVALID;
        }

        if ($now >= $this->expiresAt) {
            return OtpVerificationResult::EXPIRED;
        }

        if ($this->attempts >= self::MAX_ATTEMPTS) {
            return OtpVerificationResult::ATTEMPTS_EXCEEDED;
        }

        if (!$sameTarget || !hash_equals($this->codeHash, $codeHash)) {
            ++$this->attempts;

            return $this->attempts >= self::MAX_ATTEMPTS && $sameTarget
                ? OtpVerificationResult::ATTEMPTS_EXCEEDED
                : OtpVerificationResult::INVALID;
        }

        $this->consumedAt  = $now;
        $this->consumedNow = true;

        return OtpVerificationResult::VERIFIED;
    }

    /**
     * A new code replaces the previous one, with fresh attempts and validity.
     *
     * @throws OtpRateLimitedException       before the resend delay
     * @throws OtpChallengeNotFoundException once the challenge was used
     */
    public function resend(string $newCodeHash, OtpChannel $channel, \DateTimeImmutable $now): void
    {
        $this->assertResendable($now);

        $this->codeHash          = $newCodeHash;
        $this->attempts          = 0;
        $this->expiresAt         = $now->add(new \DateInterval(self::LIFETIME));
        $this->resendAvailableAt = $now->add(new \DateInterval(self::RESEND_DELAY));
        $this->lastChannel       = $channel;
        ++$this->sendCount;
    }

    /**
     * The checks of resend(), for a resend that ends up sending nothing (it
     * must answer exactly like one that sends).
     *
     * @throws OtpRateLimitedException       before the resend delay
     * @throws OtpChallengeNotFoundException once the challenge was used
     */
    public function assertResendable(\DateTimeImmutable $now): void
    {
        if (null !== $this->consumedAt) {
            throw new OtpChallengeNotFoundException();
        }

        if ($now < $this->resendAvailableAt) {
            throw new OtpRateLimitedException($this->resendAvailableAt);
        }
    }

    /**
     * Attaches an onboarding grant to the login challenge this verification
     * just consumed. Any other challenge, or a second grant, is refused.
     */
    public function grantOnboarding(string $tokenHash, \DateTimeImmutable $now): void
    {
        if (OtpPurpose::LOGIN !== $this->purpose || !$this->consumedNow || null !== $this->onboardingTokenHash) {
            throw new \LogicException('An onboarding grant is minted once, by the verification of a login code.');
        }

        $this->onboardingTokenHash = $tokenHash;
        $this->onboardingExpiresAt = $now->add(new \DateInterval(self::ONBOARDING_LIFETIME));
    }

    public function revokeOnboarding(\DateTimeImmutable $at): void
    {
        $this->onboardingRevokedAt ??= $at;
    }

    /** The grant exists, is not revoked and has not expired. */
    public function isOnboardingUsable(\DateTimeImmutable $now): bool
    {
        return null !== $this->onboardingTokenHash
            && null === $this->onboardingRevokedAt
            && null !== $this->onboardingExpiresAt
            && $now < $this->onboardingExpiresAt;
    }

    public function id(): OtpChallengeId
    {
        return $this->id;
    }

    public function phone(): PhoneNumber
    {
        return $this->phone;
    }

    public function purpose(): OtpPurpose
    {
        return $this->purpose;
    }

    public function subjectType(): ?OtpSubjectType
    {
        return $this->purpose->subjectType();
    }

    /** The invitation id or user id the challenge is bound to. */
    public function subjectRef(): ?string
    {
        return $this->subjectRef;
    }

    /** The address an `email_verification` code proves. */
    public function targetEmail(): ?string
    {
        return $this->targetEmail;
    }

    public function codeHash(): string
    {
        return $this->codeHash;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function sendCount(): int
    {
        return $this->sendCount;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function resendAvailableAt(): \DateTimeImmutable
    {
        return $this->resendAvailableAt;
    }

    public function lastChannel(): OtpChannel
    {
        return $this->lastChannel;
    }

    public function isSmsSuppressed(): bool
    {
        return $this->smsSuppressed;
    }

    public function consumedAt(): ?\DateTimeImmutable
    {
        return $this->consumedAt;
    }

    public function onboardingTokenHash(): ?string
    {
        return $this->onboardingTokenHash;
    }

    public function onboardingExpiresAt(): ?\DateTimeImmutable
    {
        return $this->onboardingExpiresAt;
    }

    public function onboardingRevokedAt(): ?\DateTimeImmutable
    {
        return $this->onboardingRevokedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
