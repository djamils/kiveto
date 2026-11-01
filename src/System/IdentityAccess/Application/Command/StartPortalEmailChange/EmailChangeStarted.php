<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\StartPortalEmailChange;

use App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp\ActionOtpResult;

/** The verification challenge of the new address, when the SMS code was right. */
final readonly class EmailChangeStarted
{
    public function __construct(
        public ActionOtpResult $result,
        public ?string $verificationChallengeId = null,
        public ?\DateTimeImmutable $expiresAt = null,
    ) {
    }
}
