<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * Owner accounts (IdentityAccess). The proof — an invitation code, an
 * onboarding grant or a registration grant — is passed in clear and verified
 * there, in the caller's transaction.
 */
interface PortalIdentityInterface
{
    /**
     * The owner account of the invitation's phone once the proof holds,
     * created when missing unless `createIfMissing` is false (retry check).
     * Never throws on a bad proof.
     */
    public function claimIdentity(IdentityClaimRequest $request): IdentityClaim;

    /** Revokes every invitation onboarding grant of the phone (E.164). */
    public function revokeOnboardingGrants(string $phone): void;

    /**
     * Creates the owner account of the phone a registration grant proved
     * (`email` dropped when another owner holds it), in the caller's
     * transaction. Never throws on a bad grant; when an active owner account
     * already holds the phone, IdentityAccess throws its
     * PortalAccountExistsException, which rolls the transaction back.
     */
    public function registerPortalUser(#[\SensitiveParameter] string $registrationToken, ?string $email): PortalRegistration;
}
