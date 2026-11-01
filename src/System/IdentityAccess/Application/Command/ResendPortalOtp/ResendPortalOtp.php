<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ResendPortalOtp;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A new code for the same challenge (« Renvoyer le code »), through
 * `channel`: `sms`, or `email` — honoured for a sign-in code whose phone
 * has an active owner account with a verified e-mail only. Answers an
 * OtpChallengeIssued, the same whether or not anything was sent.
 */
final readonly class ResendPortalOtp implements CommandInterface
{
    public function __construct(
        public string $challengeId,
        public string $channel,
    ) {
    }
}
