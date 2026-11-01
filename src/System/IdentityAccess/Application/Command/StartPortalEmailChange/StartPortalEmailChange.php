<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\StartPortalEmailChange;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Second step of an owner's e-mail change: the SMS code of an
 * `email_change` challenge (`challengeId` + `code`, bound to `userId`) is
 * verified, then a code is sent to `email` through a new
 * `email_verification` challenge. Also re-verifies the current address.
 * Answers an EmailChangeStarted; never throws on a wrong code.
 */
final readonly class StartPortalEmailChange implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $challengeId,
        public string $code,
        public string $email,
    ) {
    }
}
