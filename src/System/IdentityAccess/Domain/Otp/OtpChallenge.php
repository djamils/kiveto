<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Domain\Exception\OtpChallengeNotFoundException;
use App\System\IdentityAccess\Domain\Exception\OtpRateLimitedException;

/**
 * A one-time code sent to a mobile phone. Only an HMAC of the code is kept
 * (a plain digest of 10⁶ values is reversible). The code lives 10 minutes,
 * allows 5 attempts and is used once; a new one may be sent after 30 s.
 *
 * A login challenge verified for a phone that has pending invitations may
 * carry an onboarding grant: a 15-minute token that accepts those
 * invitations without a second code. It can only be minted in the very
 * verification that consumes the challenge, once.
 */
final class OtpChallenge
{
    public const int MAX_ATTEMPTS = 5;

    private const string LIFETIME            = 'PT10M';
    private const string RESEND_DELAY        = 'PT30S';
    private const string ONBOARDING_LIFETIME = 'PT15M';

    private OtpChallengeId $id;
    private PhoneNumber $phone;
    private OtpPurpose $purpose;
    private ?string $invitationRef;
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
     * @param bool $smsSuppressed the code is not sent (unknown-number SMS
     *                            budget exhausted) and never will be, even on
     *                            a resend
     */
    public static function start(
        OtpChallengeId $id,
        PhoneNumber $phone,
        OtpPurpose $purpose,
        ?string $invitationRef,
        string $codeHash,
        \DateTimeImmutable $now,
        bool $smsSuppressed = false,
    ): self {
        if ((OtpPurpose::INVITATION === $purpose) === (null === $invitationRef)) {
            throw new \InvalidArgumentException('An invitation code, and only it, is bound to an invitation.');
        }

        $challenge                      = new self();
        $challenge->id                  = $id;
        $challenge->phone               = $phone;
        $challenge->purpose             = $purpose;
        $challenge->invitationRef       = $invitationRef;
        $challenge->codeHash            = $codeHash;
        $challenge->attempts            = 0;
        $challenge->sendCount           = 1;
        $challenge->expiresAt           = $now->add(new \DateInterval(self::LIFETIME));
        $challenge->resendAvailableAt   = $now->add(new \DateInterval(self::RESEND_DELAY));
        $challenge->lastChannel         = OtpChannel::SMS;
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
        ?string $invitationRef,
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
    ): self {
        $challenge                      = new self();
        $challenge->id                  = $id;
        $challenge->phone               = $phone;
        $challenge->purpose             = $purpose;
        $challenge->invitationRef       = $invitationRef;
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
     * A code of another purpose or invitation counts as a wrong attempt.
     */
    public function verify(
        string $codeHash,
        \DateTimeImmutable $now,
        OtpPurpose $expectedPurpose,
        ?string $expectedInvitationRef = null,
    ): OtpVerificationResult {
        $sameTarget = $expectedPurpose === $this->purpose && $expectedInvitationRef === $this->invitationRef;

        if (null !== $this->consumedAt) {
            return $sameTarget && $now < $this->expiresAt && hash_equals($this->codeHash, $codeHash)
                ? OtpVerificationResult::ALREADY_VERIFIED
                : OtpVerificationResult::INVALID;
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
        if (null !== $this->consumedAt) {
            throw new OtpChallengeNotFoundException();
        }

        if ($now < $this->resendAvailableAt) {
            throw new OtpRateLimitedException($this->resendAvailableAt);
        }

        $this->codeHash          = $newCodeHash;
        $this->attempts          = 0;
        $this->expiresAt         = $now->add(new \DateInterval(self::LIFETIME));
        $this->resendAvailableAt = $now->add(new \DateInterval(self::RESEND_DELAY));
        $this->lastChannel       = $channel;
        ++$this->sendCount;
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

    public function invitationRef(): ?string
    {
        return $this->invitationRef;
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
