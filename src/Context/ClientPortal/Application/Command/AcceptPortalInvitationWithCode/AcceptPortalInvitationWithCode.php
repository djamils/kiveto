<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Command\AcceptPortalInvitationWithCode;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Link flow: the invitation found by its token, the phone proven by the
 * invitation code sent to it. Answers an AcceptOutcome and never throws on
 * a bad code (the attempt must be kept); TermsOutdatedException before any
 * write.
 */
final readonly class AcceptPortalInvitationWithCode implements CommandInterface
{
    public function __construct(
        #[\SensitiveParameter]
        public string $token,
        public string $challengeId,
        #[\SensitiveParameter]
        public string $code,
        public string $termsVersion,
    ) {
    }
}
