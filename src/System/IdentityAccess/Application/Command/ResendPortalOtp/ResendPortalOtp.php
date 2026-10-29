<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ResendPortalOtp;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A new code for the same challenge (« Renvoyer le code »), through
 * `channel` (`sms` only for now). Answers an OtpChallengeIssued.
 */
final readonly class ResendPortalOtp implements CommandInterface
{
    public function __construct(
        public string $challengeId,
        public string $channel,
    ) {
    }
}
