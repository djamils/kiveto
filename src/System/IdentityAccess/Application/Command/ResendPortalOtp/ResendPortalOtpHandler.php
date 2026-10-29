<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ResendPortalOtp;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtpHandler;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeGeneratorInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Application\Port\PortalOtpRequest;
use App\System\IdentityAccess\Domain\Exception\OtpChallengeNotFoundException;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChannel;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * The previous code stops working. A challenge created beyond the budget of
 * unknown numbers never sends anything, so a resend cannot bypass it.
 */
#[AsMessageHandler]
final readonly class ResendPortalOtpHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private OtpCodeGeneratorInterface $codes,
        private OtpCodeHasherInterface $codeHasher,
        private NotificationRequesterInterface $notifications,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ResendPortalOtp $command): OtpChallengeIssued
    {
        $challenge = Uuid::isValid($command->challengeId)
            ? $this->challenges->findByIdForUpdate(OtpChallengeId::fromString($command->challengeId))
            : null;

        if (null === $challenge) {
            throw new OtpChallengeNotFoundException();
        }

        $code = $this->codes->generate();
        $challenge->resend($this->codeHasher->hash($code), OtpChannel::from($command->channel), $this->clock->now());
        $this->challenges->save($challenge);

        if (!$challenge->isSmsSuppressed()) {
            $this->notifications->requestPortalOtp(new PortalOtpRequest(
                challengeId: $challenge->id()->toString(),
                phone: $challenge->phone()->toString(),
                code: $code,
                sendCount: $challenge->sendCount(),
                validityMinutes: RequestPortalOtpHandler::VALIDITY_MINUTES,
            ));
        }

        return new OtpChallengeIssued($challenge->id()->toString(), $challenge->expiresAt(), $challenge->resendAvailableAt());
    }
}
