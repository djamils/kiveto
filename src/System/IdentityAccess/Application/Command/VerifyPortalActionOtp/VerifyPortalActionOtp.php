<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\VerifyPortalActionOtp;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Verifies the SMS code confirming an action (`purpose` = `email_change` or
 * `account_deletion`) of the signed-in owner `userId`. Answers an
 * ActionOtpResult and never throws on a wrong code: the attempt is
 * committed. A verified code is consumed.
 */
final readonly class VerifyPortalActionOtp implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $challengeId,
        public string $code,
        public string $purpose,
    ) {
    }
}
