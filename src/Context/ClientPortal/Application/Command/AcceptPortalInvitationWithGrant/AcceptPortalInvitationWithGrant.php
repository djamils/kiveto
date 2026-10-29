<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithGrant;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Phone flow: an invitation listed after a phone sign-in, accepted with the
 * onboarding grant that sign-in earned (no second code). Answers an
 * AcceptOutcome and never throws on a bad grant; TermsOutdatedException
 * before any write.
 */
final readonly class AcceptPortalInvitationWithGrant implements CommandInterface
{
    public function __construct(
        public string $invitationId,
        #[\SensitiveParameter]
        public string $onboardingToken,
        public string $termsVersion,
    ) {
    }
}
