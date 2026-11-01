<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RequestPortalActionOtp;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Sends to the phone of a signed-in owner the SMS code that confirms one of
 * their actions: `purpose` is `email_change` or `account_deletion`. The
 * challenge is bound to `userId`. Answers an OtpChallengeIssued; throws
 * PortalAccountUnavailableException when the account is no longer an active
 * owner account.
 */
final readonly class RequestPortalActionOtp implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $purpose,
    ) {
    }
}
