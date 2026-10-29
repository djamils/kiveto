<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RequestPortalOtp;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Exception\PhoneNotSupportedException;
use App\System\IdentityAccess\Application\Port\MobilePhonePolicyInterface;
use App\System\IdentityAccess\Application\Port\NotificationRequesterInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeGeneratorInterface;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Application\Port\PendingPortalInvitationsInterface;
use App\System\IdentityAccess\Application\Port\PortalOtpRequest;
use App\System\IdentityAccess\Application\Port\UnknownNumberSmsBudgetInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChallenge;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A sign-in code goes to a phone of the Portal's coverage only. To a phone
 * with neither an active owner account nor a pending invitation (a phone a
 * clinic chose counts as known), it spends the global budget of unknown
 * numbers: once exhausted, the challenge exists (same answer) but no SMS
 * leaves, now or on a resend. An invitation code goes to the phone a clinic
 * chose and never spends that budget.
 */
#[AsMessageHandler]
final readonly class RequestPortalOtpHandler
{
    public const int VALIDITY_MINUTES = 10;

    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private UserRepositoryInterface $users,
        private MobilePhonePolicyInterface $mobilePolicy,
        private UnknownNumberSmsBudgetInterface $unknownNumberBudget,
        private PendingPortalInvitationsInterface $pendingInvitations,
        private OtpCodeGeneratorInterface $codes,
        private OtpCodeHasherInterface $codeHasher,
        private NotificationRequesterInterface $notifications,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RequestPortalOtp $command): OtpChallengeIssued
    {
        $phone   = PhoneNumber::fromString($command->phone);
        $purpose = OtpPurpose::from($command->purpose);
        $now     = $this->clock->now();

        $suppressed = false;
        if (OtpPurpose::LOGIN === $purpose) {
            if (!$this->mobilePolicy->isPortalMobile($phone->toString())) {
                throw new PhoneNotSupportedException();
            }

            $suppressed = !$this->isKnown($phone) && !$this->unknownNumberBudget->consume();
        }

        $code      = $this->codes->generate();
        $challenge = OtpChallenge::start(
            OtpChallengeId::fromString($this->uuids->generate()),
            $phone,
            $purpose,
            $command->invitationRef,
            $this->codeHasher->hash($code),
            $now,
            $suppressed,
        );
        $this->challenges->save($challenge);

        if (!$suppressed) {
            $this->notifications->requestPortalOtp(new PortalOtpRequest(
                challengeId: $challenge->id()->toString(),
                phone: $phone->toString(),
                code: $code,
                sendCount: $challenge->sendCount(),
                validityMinutes: self::VALIDITY_MINUTES,
            ));
        }

        return new OtpChallengeIssued($challenge->id()->toString(), $challenge->expiresAt(), $challenge->resendAvailableAt());
    }

    private function isKnown(PhoneNumber $phone): bool
    {
        return UserStatus::ACTIVE === $this->users->findByPhoneAndType($phone, UserType::PORTAL)?->status()
            || [] !== $this->pendingInvitations->listForPhone($phone);
    }
}
