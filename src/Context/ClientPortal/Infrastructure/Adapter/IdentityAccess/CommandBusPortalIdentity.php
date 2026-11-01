<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Infrastructure\Adapter\IdentityAccess;

use App\Context\ClientPortal\Application\Port\IdentityClaim;
use App\Context\ClientPortal\Application\Port\IdentityClaimRequest;
use App\Context\ClientPortal\Application\Port\IdentityClaimResult;
use App\Context\ClientPortal\Application\Port\PortalIdentityInterface;
use App\Context\ClientPortal\Application\Port\PortalRegistration;
use App\Context\ClientPortal\Application\Port\PortalRegistrationResult;
use App\Shared\Application\Bus\CommandBusInterface;
use App\System\IdentityAccess\Application\Command\ClaimPortalIdentity\ClaimOutcome;
use App\System\IdentityAccess\Application\Command\ClaimPortalIdentity\ClaimPortalIdentity;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\PortalUserRegistration;
use App\System\IdentityAccess\Application\Command\RegisterPortalUser\RegisterPortalUser;
use App\System\IdentityAccess\Application\Command\RevokeOnboardingGrantsForPhone\RevokeOnboardingGrantsForPhone;

/**
 * IdentityAccess commands, dispatched inside the caller's transaction (the
 * command bus nests): a later failure of the acceptance or the registration
 * rolls the claim or the new account back. IdentityAccess's
 * PortalAccountExistsException goes through untouched.
 */
final readonly class CommandBusPortalIdentity implements PortalIdentityInterface
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    public function claimIdentity(IdentityClaimRequest $request): IdentityClaim
    {
        $outcome = $this->commandBus->dispatch(new ClaimPortalIdentity(
            invitationRef: $request->invitationId,
            expectedPhone: $request->expectedPhone,
            challengeId: $request->challengeId,
            code: $request->code,
            onboardingToken: $request->onboardingToken,
            email: $request->email,
            createIfMissing: $request->createIfMissing,
        ));
        \assert($outcome instanceof ClaimOutcome);

        return new IdentityClaim(IdentityClaimResult::from($outcome->result->value), $outcome->userId);
    }

    public function revokeOnboardingGrants(string $phone): void
    {
        $this->commandBus->dispatch(new RevokeOnboardingGrantsForPhone($phone));
    }

    public function registerPortalUser(#[\SensitiveParameter] string $registrationToken, ?string $email): PortalRegistration
    {
        $registration = $this->commandBus->dispatch(new RegisterPortalUser($registrationToken, $email));
        \assert($registration instanceof PortalUserRegistration);

        return new PortalRegistration(PortalRegistrationResult::from($registration->result->value), $registration->userId);
    }
}
