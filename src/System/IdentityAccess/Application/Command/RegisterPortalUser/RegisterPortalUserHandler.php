<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RegisterPortalUser;

use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Service\PortalAccountRegistrar;
use App\System\IdentityAccess\Domain\Exception\PortalAccountExistsException;
use App\System\IdentityAccess\Domain\Otp\OnboardingPurpose;
use App\System\IdentityAccess\Domain\Otp\OtpChallengeRepositoryInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserStatus;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Order (S7a): the grant locked → the per-phone mutex → the phone's account
 * read past the snapshot and locked → the grant consumed and the account
 * created. Two registrations of one phone queue on the mutex: the second
 * finds the first one's account (409). A grant is only consumed together
 * with the account it creates; once consumed, it answers the account it
 * created while it lives (the phone's challenges, grant included, are
 * deleted with an account, so a consumed grant and an active account of its
 * phone always go together).
 */
#[AsMessageHandler]
final readonly class RegisterPortalUserHandler
{
    public function __construct(
        private OtpChallengeRepositoryInterface $challenges,
        private UserRepositoryInterface $users,
        private OpaqueTokenHasherInterface $tokenHasher,
        private PortalAccountRegistrar $registrar,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws PortalAccountExistsException
     */
    public function __invoke(RegisterPortalUser $command): PortalUserRegistration
    {
        $now       = $this->clock->now();
        $challenge = $this->challenges->findByOnboardingHashForUpdate($this->tokenHasher->hash($command->registrationToken), OnboardingPurpose::REGISTRATION);

        if (null === $challenge || !$challenge->isOnboardingUsable($now)) {
            return new PortalUserRegistration(RegistrationResult::ONBOARDING_INVALID);
        }

        $phone = $challenge->phone();
        $this->users->lockPortalPhone($phone);
        $existing = $this->users->findByPhoneAndTypeForUpdate($phone, UserType::PORTAL);

        if (null !== $challenge->onboardingConsumedAt()) {
            return UserStatus::ACTIVE === $existing?->status()
                ? new PortalUserRegistration(RegistrationResult::REPLAYED, $existing->id()->toString())
                : new PortalUserRegistration(RegistrationResult::ONBOARDING_INVALID);
        }

        if (null !== $existing) {
            return UserStatus::ACTIVE === $existing->status()
                ? throw new PortalAccountExistsException()
                : new PortalUserRegistration(RegistrationResult::ACCOUNT_DISABLED);
        }

        $challenge->consumeRegistrationGrant($now);
        $this->challenges->save($challenge);

        return new PortalUserRegistration(RegistrationResult::REGISTERED, $this->registrar->register($phone, $command->email, $now));
    }
}
