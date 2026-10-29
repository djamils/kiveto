<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ClaimPortalIdentity;

use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Domain\ValueObject\PhoneNumber;
use App\System\IdentityAccess\Application\Port\OtpCodeHasherInterface;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeId;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Otp\OtpPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpVerificationResult;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\User;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Never acts on a phone without a proof verified here, in the caller's
 * transaction: it cannot serve as « an account for any phone ». Concurrent
 * claims of one phone queue on the per-phone mutex, taken before any other
 * phone-related lock; the account is then read authoritatively and, when
 * missing, inserted with SQL — an owner e-mail taken meanwhile by another
 * phone only costs the new account its e-mail.
 */
#[AsMessageHandler]
final readonly class ClaimPortalIdentityHandler
{
    /** Width of `identity_access__users.email`; client files allow longer ones. */
    private const int EMAIL_MAX_LENGTH = 180;

    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private UserRepositoryInterface $users,
        private OtpCodeHasherInterface $codeHasher,
        private OpaqueTokenHasherInterface $tokenHasher,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ClaimPortalIdentity $command): ClaimOutcome
    {
        $phone = PhoneNumber::fromString($command->expectedPhone);
        $now   = $this->clock->now();
        $proof = $this->verifyProof($command, $phone, $now);

        if (!$proof->isProven()) {
            return new ClaimOutcome($proof);
        }

        $this->users->lockPortalPhone($phone);
        $existing = $this->users->findByPhoneAndType($phone, UserType::PORTAL);

        if (null !== $existing) {
            return UserStatus::ACTIVE === $existing->status()
                ? new ClaimOutcome($proof, $existing->id()->toString())
                : new ClaimOutcome(ClaimResult::ACCOUNT_DISABLED);
        }

        if (!$command->createIfMissing) {
            return new ClaimOutcome($proof);
        }

        return new ClaimOutcome($proof, $this->register($phone, $this->freeEmail($command->email), $now));
    }

    private function verifyProof(ClaimPortalIdentity $command, PhoneNumber $phone, \DateTimeImmutable $now): ClaimResult
    {
        if (null !== $command->onboardingToken) {
            $challenge = $this->challenges->findByOnboardingHashForUpdate($this->tokenHasher->hash($command->onboardingToken));

            return match (true) {
                null === $challenge || !$challenge->isOnboardingUsable($now) => ClaimResult::ONBOARDING_INVALID,
                !$challenge->phone()->equals($phone)                         => ClaimResult::PHONE_MISMATCH,
                default                                                      => ClaimResult::VERIFIED,
            };
        }

        if (null === $command->challengeId || null === $command->code) {
            throw new \InvalidArgumentException('A claim needs a code or an onboarding grant.');
        }

        $challenge = Uuid::isValid($command->challengeId)
            ? $this->challenges->findByIdForUpdate(OtpChallengeId::fromString($command->challengeId))
            : null;

        if (null === $challenge) {
            return ClaimResult::OTP_INVALID;
        }

        $verification = $challenge->verify($this->codeHasher->hash($command->code), $now, OtpPurpose::INVITATION, $command->invitationRef);
        $this->challenges->save($challenge);

        if ($verification->isProof() && !$challenge->phone()->equals($phone)) {
            return ClaimResult::PHONE_MISMATCH;
        }

        return match ($verification) {
            OtpVerificationResult::VERIFIED          => ClaimResult::VERIFIED,
            OtpVerificationResult::ALREADY_VERIFIED  => ClaimResult::ALREADY_VERIFIED,
            OtpVerificationResult::INVALID           => ClaimResult::OTP_INVALID,
            OtpVerificationResult::EXPIRED           => ClaimResult::OTP_EXPIRED,
            OtpVerificationResult::ATTEMPTS_EXCEEDED => ClaimResult::OTP_ATTEMPTS_EXCEEDED,
        };
    }

    /**
     * The client's address, unless another owner account already holds it
     * (families) or it does not fit an account's address column.
     */
    private function freeEmail(?string $email): ?string
    {
        $email = null === $email ? '' : mb_strtolower(mb_trim($email));

        if ('' === $email || mb_strlen($email) > self::EMAIL_MAX_LENGTH || null !== $this->users->findByEmailAndType($email, UserType::PORTAL)) {
            return null;
        }

        return $email;
    }

    private function register(PhoneNumber $phone, ?string $email, \DateTimeImmutable $now): string
    {
        $user = User::registerPortal(UserId::fromString($this->uuids->generate()), $phone, $email, $now);

        if (!$this->users->addPortalIfPhoneFree($user)) {
            // The phone is locked by us: only the e-mail key can have refused
            // it — another phone took the address meanwhile.
            $user = User::registerPortal(UserId::fromString($this->uuids->generate()), $phone, null, $now);

            if (!$this->users->addPortalIfPhoneFree($user)) {
                throw new \LogicException(\sprintf('The owner account of "%s" could not be created.', $phone->toString()));
            }
        }

        $this->domainEventPublisher->publish($user);

        return $user->id()->toString();
    }
}
