<?php

declare(strict_types=1);

namespace App\Presentation\Api\Portal\View;

/** The `email_verification` challenge whose code was sent to the new address. */
final readonly class EmailVerificationChallengeView
{
    public function __construct(
        public string $challengeId,
    ) {
    }
}
