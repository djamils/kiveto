<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\StartPortalEmailChange;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtpHandler;
use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeGeneratorInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Application\Port\PortalEmailVerificationRequest;
use App\System\IdentityAccess\Application\Service\ActionOtpVerifier;
use App\System\IdentityAccess\Application\Service\PortalOwnerAccounts;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * No collision is checked here (D8): whether another account holds the
 * address is only told at the verification, once the address is proven.
 */
#[AsMessageHandler]
final readonly class StartPortalEmailChangeHandler
{
    /** Width of `identity_access__users.email`. */
    public const int EMAIL_MAX_LENGTH = 180;

    public function __construct(
        private ActionOtpVerifier $verifier,
        private PortalOwnerAccounts $owners,
        private OtpChallengeRepositoryInterface $challenges,
        private OtpCodeGeneratorInterface $codes,
        private OtpCodeHasherInterface $codeHasher,
        private NotificationRequesterInterface $notifications,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(StartPortalEmailChange $command): EmailChangeStarted
    {
        $email = self::normalise($command->email);
        $user  = $this->owners->active($command->userId);

        $verification = $this->verifier->verify($command->userId, $command->challengeId, $command->code, OtpPurpose::EMAIL_CHANGE);
        if (ActionOtpResult::VERIFIED !== $verification->result) {
            return new EmailChangeStarted($verification->result);
        }

        $phone = $user->phone();
        \assert(null !== $phone);

        $code      = $this->codes->generate();
        $challenge = OtpChallenge::start(
            OtpChallengeId::fromString($this->uuids->generate()),
            $phone,
            OtpPurpose::EMAIL_VERIFICATION,
            $user->id()->toString(),
            $this->codeHasher->hash($code),
            $this->clock->now(),
            targetEmail: $email,
        );
        $this->challenges->save($challenge);

        $this->notifications->requestPortalEmailVerification(new PortalEmailVerificationRequest(
            challengeId: $challenge->id()->toString(),
            userId: $user->id()->toString(),
            email: $email,
            code: $code,
            validityMinutes: RequestPortalOtpHandler::VALIDITY_MINUTES,
        ));

        return new EmailChangeStarted(ActionOtpResult::VERIFIED, $challenge->id()->toString(), $challenge->expiresAt());
    }

    /**
     * Addresses are compared without case (the column's collation): stored
     * in lower case.
     */
    private static function normalise(string $email): string
    {
        $email = mb_strtolower(mb_trim($email));

        if (mb_strlen($email) > self::EMAIL_MAX_LENGTH || false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('The e-mail address is not valid.');
        }

        return $email;
    }
}
