<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Domain\Otp;

use App\Shared\Domain\ValueObject\PhoneNumber;

interface OtpChallengeRepositoryInterface
{
    /** Inserts a new challenge or writes the state of a known one. */
    public function save(OtpChallenge $challenge): void;

    public function findById(OtpChallengeId $id): ?OtpChallenge;

    /** Locked until the end of the transaction: two verifications of one challenge queue. */
    public function findByIdForUpdate(OtpChallengeId $id): ?OtpChallenge;

    /**
     * The challenge carrying this onboarding grant, locked; only a grant of
     * that purpose is found (neither route accepts the other's grant).
     */
    public function findByOnboardingHashForUpdate(string $tokenHash, OnboardingPurpose $purpose): ?OtpChallenge;

    /**
     * Revokes every live `invitation` grant of the phone; returns how many.
     * Registration grants are left alone.
     */
    public function revokeOnboardingGrantsForPhone(PhoneNumber $phone, \DateTimeImmutable $at): int;

    /**
     * Deletes every challenge sent to the phone or bound to the user (their
     * onboarding grants with them); returns how many.
     */
    public function deleteForPhoneOrUser(PhoneNumber $phone, string $userId): int;
}
