<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
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
 * here: replays are only honoured by invitation acceptances.
 */
#[AsMessageHandler]
final readonly class VerifyPortalLoginOtpHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private UserRepositoryInterface $users,
        private OtpCodeHasherInterface $codeHasher,
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

        $verification = $challenge->verify($this->codeHasher->hash($command->code), $this->clock->now(), OtpPurpose::LOGIN);
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

        return match (true) {
            null === $user                         => new LoginOtpOutcome(LoginOtpResult::REGISTRATION_REQUIRED),
            UserStatus::ACTIVE !== $user->status() => new LoginOtpOutcome(LoginOtpResult::ACCOUNT_DISABLED),
            default                                => new LoginOtpOutcome(LoginOtpResult::AUTHENTICATED, $user->id()->toString()),
        };
    }
}
