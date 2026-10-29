<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokeOnboardingGrantsForPhone;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Revokes every live onboarding grant of a phone (E.164): its last pending
 * invitation was accepted. Idempotent; answers how many were revoked.
 */
final readonly class RevokeOnboardingGrantsForPhone implements CommandInterface
{
    public function __construct(
        public string $phone,
    ) {
    }
}
