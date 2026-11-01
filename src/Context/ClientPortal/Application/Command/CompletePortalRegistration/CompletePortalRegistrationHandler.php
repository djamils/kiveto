<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\CompletePortalRegistration;

use App\Context\ClientPortal\Application\Port\PortalIdentityInterface;
use App\Context\ClientPortal\Application\Port\PortalRegistrationResult;
use App\Context\ClientPortal\Domain\Exception\TermsOutdatedException;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Context\ClientPortal\Domain\Profile\OwnerProfileRepositoryInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * One transaction (S7a): terms check → the account created by IdentityAccess
 * (grant locked and consumed, per-phone mutex, existing account refused) →
 * the owner profile from the entered names. A replay answers the account
 * the grant created and writes nothing: its profile is never overwritten.
 */
#[AsMessageHandler]
final readonly class CompletePortalRegistrationHandler
{
    public function __construct(
        private PortalIdentityInterface $identity,
        private OwnerProfileRepositoryInterface $profiles,
        private ClockInterface $clock,
        #[Autowire('%env(PORTAL_TERMS_VERSION)%')]
        private string $currentTermsVersion,
    ) {
    }

    /**
     * @throws TermsOutdatedException    before any write
     * @throws \InvalidArgumentException for an empty or too long name (rolled back)
     */
    public function __invoke(CompletePortalRegistration $command): RegistrationOutcome
    {
        if ($command->termsVersion !== $this->currentTermsVersion) {
            throw new TermsOutdatedException($command->termsVersion, $this->currentTermsVersion);
        }

        $registration = $this->identity->registerPortalUser($command->registrationToken, $command->email);

        if (PortalRegistrationResult::REGISTERED === $registration->result) {
            $userId = $registration->userId ?? throw new \LogicException('A registration names the new owner account.');
            $this->profiles->save(OwnerProfile::register($userId, $command->firstName, $command->lastName, $command->termsVersion, $this->clock->now()));
        }

        return new RegistrationOutcome(match ($registration->result) {
            PortalRegistrationResult::REGISTERED         => RegistrationResult::REGISTERED,
            PortalRegistrationResult::REPLAYED           => RegistrationResult::REPLAYED,
            PortalRegistrationResult::ONBOARDING_INVALID => RegistrationResult::ONBOARDING_INVALID,
            PortalRegistrationResult::ACCOUNT_DISABLED   => RegistrationResult::ACCOUNT_DISABLED,
        }, $registration->userId);
    }
}
