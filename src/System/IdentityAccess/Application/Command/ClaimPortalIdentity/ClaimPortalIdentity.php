<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ClaimPortalIdentity;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The owner account of a phone proven for an invitation. The proof is
 * verified here, in the caller's transaction: either the invitation code
 * (`challengeId` + `code`, a challenge bound to `invitationRef`) or an
 * onboarding grant (`onboardingToken`, in clear) whose phone must be
 * `expectedPhone`. Then: the active owner account of the phone, or a new
 * one (`email` copied unless another owner holds it). With
 * `createIfMissing` false (retry check), only an existing account is
 * answered. Answers a ClaimOutcome; never throws on a bad proof.
 */
final readonly class ClaimPortalIdentity implements CommandInterface
{
    public function __construct(
        public string $invitationRef,
        public string $expectedPhone,
        public ?string $challengeId = null,
        #[\SensitiveParameter]
        public ?string $code = null,
        #[\SensitiveParameter]
        public ?string $onboardingToken = null,
        public ?string $email = null,
        public bool $createIfMissing = true,
    ) {
    }
}
