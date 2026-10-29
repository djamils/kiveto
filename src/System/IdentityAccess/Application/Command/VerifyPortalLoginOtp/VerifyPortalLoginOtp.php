<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifyPortalLoginOtp;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Checks a sign-in code. Answers a LoginOtpOutcome and never throws on a
 * wrong code: the attempt it counts must be committed.
 */
final readonly class VerifyPortalLoginOtp implements CommandInterface
{
    public function __construct(
        public string $challengeId,
        public string $code,
    ) {
    }
}
