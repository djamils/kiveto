<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Service;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpVerificationResult;
use Symfony\Component\Uid\Uuid;

/**
 * Verifies a code bound to a user (action codes and e-mail verification),
 * the challenge locked, and saves the attempt whatever the outcome: the
 * caller's transaction must commit it. A replay of a used code is refused.
 */
final readonly class ActionOtpVerifier
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private OtpCodeHasherInterface $codeHasher,
        private ClockInterface $clock,
    ) {
    }

    public function verify(string $userId, string $challengeId, string $code, OtpPurpose $purpose): ActionOtpVerification
    {
        $challenge = Uuid::isValid($challengeId)
            ? $this->challenges->findByIdForUpdate(OtpChallengeId::fromString($challengeId))
            : null;

        if (null === $challenge) {
            return new ActionOtpVerification(ActionOtpResult::OTP_INVALID, null);
        }

        $verification = $challenge->verify($this->codeHasher->hash($code), $this->clock->now(), $purpose, $userId);
        $this->challenges->save($challenge);

        $result = match ($verification) {
            OtpVerificationResult::VERIFIED => ActionOtpResult::VERIFIED,
            OtpVerificationResult::INVALID, OtpVerificationResult::ALREADY_VERIFIED => ActionOtpResult::OTP_INVALID,
            OtpVerificationResult::EXPIRED           => ActionOtpResult::OTP_EXPIRED,
            OtpVerificationResult::ATTEMPTS_EXCEEDED => ActionOtpResult::OTP_ATTEMPTS_EXCEEDED,
        };

        return new ActionOtpVerification($result, ActionOtpResult::VERIFIED === $result ? $challenge : null);
    }
}
