<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ConfirmPortalEmailChange;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Last step of an owner's e-mail change: the code sent to the new address
 * (an `email_verification` challenge bound to `userId`) is verified and the
 * address becomes the account's verified e-mail. Answers an
 * EmailChangeResult; never throws on a wrong code.
 */
final readonly class ConfirmPortalEmailChange implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $challengeId,
        public string $code,
    ) {
    }
}
