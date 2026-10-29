<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/**
 * Either `challengeId` + `code` (invitation code) or `onboardingToken`
 * (grant from a phone sign-in).
 */
final readonly class IdentityClaimRequest
{
    public function __construct(
        public string $invitationId,
        public string $expectedPhone,
        public ?string $challengeId,
        #[\SensitiveParameter]
        public ?string $code,
        #[\SensitiveParameter]
        public ?string $onboardingToken,
        public ?string $email,
        public bool $createIfMissing,
    ) {
    }
}
