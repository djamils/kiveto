<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp;

use App\System\IdentityAccess\Application\Port\PendingPortalInvitation;

final readonly class LoginOtpOutcome
{
    /**
     * @param string|null                   $userId             the active owner account, when authenticated only
     * @param string|null                   $onboardingToken    in clear, when invitations are pending (an
     *                                                          `invitation` grant) or when the phone must
     *                                                          register (a `registration` grant): it only
     *                                                          exists in this answer
     * @param list<PendingPortalInvitation> $pendingInvitations
     */
    public function __construct(
        public LoginOtpResult $result,
        public ?string $userId = null,
        #[\SensitiveParameter]
        public ?string $onboardingToken = null,
        public ?\DateTimeImmutable $onboardingExpiresAt = null,
        public array $pendingInvitations = [],
    ) {
    }
}
