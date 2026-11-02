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
use App\System\IdentityAccess\Application\Service\UnknownNumberSmsGate;
use App\System\IdentityAccess\Domain\Exception\OtpChallengeNotFoundException;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChannel;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * The previous code stops working. A challenge created beyond the budget of
 * unknown numbers never sends anything, so a resend cannot bypass it; a
 * sign-in code resent by SMS to an unknown number spends that budget again
 * (network, device hint, global cap, in this order) and, refused, sends
 * nothing — the answer is the same.
 *
 * A verified e-mail is a second sign-in factor (D6): `email` sends a login
 * code to the verified address of the phone's active owner account. Any
 * other `email` resend — no such address, an action code — and any resend
 * of an e-mail verification code (it must reach the address it proves,
 * never the phone) changes nothing: the current code stays valid, and the
 * answer looks like a resend so that it tells nothing about the account.
 */
#[AsMessageHandler]
final readonly class ResendPortalOtpHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private UserRepositoryInterface $users,
        private OtpCodeGeneratorInterface $codes,
        private OtpCodeHasherInterface $codeHasher,
        private NotificationRequesterInterface $notifications,
        private ClockInterface $clock,
        private UnknownNumberSmsGate $unknownNumbers,
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

        $channel = OtpChannel::from($command->channel);
        $now     = $this->clock->now();
        $email   = OtpChannel::EMAIL === $channel ? $this->verifiedEmailOf($challenge) : null;

        if (OtpPurpose::EMAIL_VERIFICATION === $challenge->purpose() || (OtpChannel::EMAIL === $channel && null === $email)) {
            $challenge->assertResendable($now);

            return new OtpChallengeIssued(
                $challenge->id()->toString(),
                $now->add(new \DateInterval(OtpChallenge::LIFETIME)),
                $now->add(new \DateInterval(OtpChallenge::RESEND_DELAY)),
            );
        }

        $code = $this->codes->generate();
        $challenge->resend($this->codeHasher->hash($code), $channel, $now);
        $this->challenges->save($challenge);

        $request = new PortalOtpRequest(
            challengeId: $challenge->id()->toString(),
            phone: $challenge->phone()->toString(),
            code: $code,
            sendCount: $challenge->sendCount(),
            validityMinutes: RequestPortalOtpHandler::VALIDITY_MINUTES,
            purpose: $challenge->purpose()->value,
        );

        if (null !== $email) {
            $this->notifications->requestPortalOtpByEmail($request, $email);
        } elseif ($this->maySendSms($challenge, $command)) {
            $this->notifications->requestPortalOtp($request);
        }

        return new OtpChallengeIssued($challenge->id()->toString(), $challenge->expiresAt(), $challenge->resendAvailableAt());
    }

    /**
     * A sign-in code resent to an unknown number spends its budget like the
     * request did (UnknownNumberSmsGate); one created beyond it is never sent.
     */
    private function maySendSms(OtpChallenge $challenge, ResendPortalOtp $command): bool
    {
        if ($challenge->isSmsSuppressed()) {
            return false;
        }

        return OtpPurpose::LOGIN !== $challenge->purpose() || $this->unknownNumbers->allows($challenge->phone(), $command->requestContext);
    }

    /** The address a login code may be resent to: never for an action code. */
    private function verifiedEmailOf(OtpChallenge $challenge): ?string
    {
        if (OtpPurpose::LOGIN !== $challenge->purpose()) {
            return null;
        }

        $user = $this->users->findByPhoneAndType($challenge->phone(), UserType::PORTAL);

        return null !== $user && UserStatus::ACTIVE === $user->status() && $user->hasVerifiedEmail() ? $user->email() : null;
    }
}
