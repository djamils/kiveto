<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * Owner accounts (IdentityAccess). The proof — an invitation code or an
 * onboarding grant — is passed in clear and verified there, in the caller's
 * transaction.
 */
interface PortalIdentityInterface
{
    /**
     * The owner account of the invitation's phone once the proof holds,
     * created when missing unless `createIfMissing` is false (retry check).
     * Never throws on a bad proof.
     */
    public function claimIdentity(IdentityClaimRequest $request): IdentityClaim;

    /** Revokes every onboarding grant of the phone (E.164). */
    public function revokeOnboardingGrants(string $phone): void;
}
