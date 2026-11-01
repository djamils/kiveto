<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp;

use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Application\Port\PendingPortalInvitationsInterface;
use App\System\IdentityAccess\Domain\Otp\OnboardingPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpVerificationResult;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Decides, in the transaction that consumes the challenge, what a sign-in
 * code opens. Only the phone's owner reaches the account-specific answers,
 * after a right code. A login code re-presented after its use is invalid
 * here: replays are only honoured by invitation acceptances. When the phone
 * has pending invitations (and its account is not disabled), the challenge
 * is given its one onboarding grant (`invitation`), in this same
 * transaction; a phone with neither account nor invitation gets a
 * `registration` grant instead, which creates its account once.
 */
#[AsMessageHandler]
final readonly class VerifyPortalLoginOtpHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private UserRepositoryInterface $users,
        private OtpCodeHasherInterface $codeHasher,
        private OpaqueTokenHasherInterface $tokenHasher,
        private PendingPortalInvitationsInterface $pendingInvitations,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(VerifyPortalLoginOtp $command): LoginOtpOutcome
    {
        $challenge = Uuid::isValid($command->challengeId)
            ? $this->challenges->findByIdForUpdate(OtpChallengeId::fromString($command->challengeId))
            : null;

        if (null === $challenge) {
            return new LoginOtpOutcome(LoginOtpResult::OTP_INVALID);
        }

        $now          = $this->clock->now();
        $verification = $challenge->verify($this->codeHasher->hash($command->code), $now, OtpPurpose::LOGIN);
        $this->challenges->save($challenge);

        $failure = match ($verification) {
            OtpVerificationResult::VERIFIED => null,
            OtpVerificationResult::INVALID, OtpVerificationResult::ALREADY_VERIFIED => LoginOtpResult::OTP_INVALID,
            OtpVerificationResult::EXPIRED           => LoginOtpResult::OTP_EXPIRED,
            OtpVerificationResult::ATTEMPTS_EXCEEDED => LoginOtpResult::OTP_ATTEMPTS_EXCEEDED,
        };

        if (null !== $failure) {
            return new LoginOtpOutcome($failure);
        }

        $user = $this->users->findByPhoneAndType($challenge->phone(), UserType::PORTAL);

        if (null !== $user && UserStatus::ACTIVE !== $user->status()) {
            return new LoginOtpOutcome(LoginOtpResult::ACCOUNT_DISABLED);
        }

        $pending = $this->pendingInvitations->listForPhone($challenge->phone());

        if (null !== $user) {
            $grant = [] === $pending ? null : $this->grant($challenge, OnboardingPurpose::INVITATION, $now);

            return new LoginOtpOutcome(LoginOtpResult::AUTHENTICATED, $user->id()->toString(), $grant, $challenge->onboardingExpiresAt(), $pending);
        }

        return [] === $pending
            ? new LoginOtpOutcome(LoginOtpResult::REGISTRATION_REQUIRED, null, $this->grant($challenge, OnboardingPurpose::REGISTRATION, $now), $challenge->onboardingExpiresAt())
            : new LoginOtpOutcome(LoginOtpResult::INVITATION_PENDING, null, $this->grant($challenge, OnboardingPurpose::INVITATION, $now), $challenge->onboardingExpiresAt(), $pending);
    }

    /** The clear onboarding token; only its digest is kept on the challenge. */
    private function grant(OtpChallenge $challenge, OnboardingPurpose $purpose, \DateTimeImmutable $now): string
    {
        $token = $this->tokenHasher->newSecret();
        $challenge->grantOnboarding($this->tokenHasher->hash($token), $purpose, $now);
        $this->challenges->save($challenge);

        return $token;
    }
}
