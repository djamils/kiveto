<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RequestPortalActionOtp;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\OtpChallengeIssued;
use App\System\IdentityAccess\Application\Command\RequestPortalOtp\RequestPortalOtpHandler;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeGeneratorInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Application\Port\PortalOtpRequest;
use App\System\IdentityAccess\Application\Port\PortalTestPhonesInterface;
use App\System\IdentityAccess\Application\Service\PortalOwnerAccounts;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The phone is the account's, already proven: neither the mobile coverage
 * nor the unknown-number budget applies. The API's per-phone and per-IP
 * quotas are consumed before dispatching. A test phone
 * (PortalTestPhonesInterface) gets the fixed code and is sent nothing.
 */
#[AsMessageHandler]
final readonly class RequestPortalActionOtpHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private PortalOwnerAccounts $owners,
        private OtpCodeGeneratorInterface $codes,
        private OtpCodeHasherInterface $codeHasher,
        private NotificationRequesterInterface $notifications,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private PortalTestPhonesInterface $testPhones,
    ) {
    }

    public function __invoke(RequestPortalActionOtp $command): OtpChallengeIssued
    {
        $purpose = OtpPurpose::from($command->purpose);

        if (!$purpose->isAction()) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an action confirmed by an SMS code.', $purpose->value));
        }

        $user  = $this->owners->active($command->userId);
        $phone = $user->phone();
        \assert(null !== $phone);

        $fixedCode = $this->testPhones->fixedCodeFor($phone, $purpose);
        $code      = $fixedCode ?? $this->codes->generate();
        $challenge = OtpChallenge::start(
            OtpChallengeId::fromString($this->uuids->generate()),
            $phone,
            $purpose,
            $user->id()->toString(),
            $this->codeHasher->hash($code),
            $this->clock->now(),
        );
        $this->challenges->save($challenge);

        if (null === $fixedCode) {
            $this->notifications->requestPortalOtp(new PortalOtpRequest(
                challengeId: $challenge->id()->toString(),
                phone: $phone->toString(),
                code: $code,
                sendCount: $challenge->sendCount(),
                validityMinutes: RequestPortalOtpHandler::VALIDITY_MINUTES,
                purpose: $purpose->value,
            ));
        }

        return new OtpChallengeIssued($challenge->id()->toString(), $challenge->expiresAt(), $challenge->resendAvailableAt());
    }
}
