<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ActivateInvitedUser;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The invited person chose a password from the link. The handler returns
 * an ActivationResult; an unusable link throws InvalidAccountTokenException,
 * a refused password UnacceptablePasswordException.
 */
final readonly class ActivateInvitedUser implements CommandInterface
{
    public function __construct(
        public string $token,
        #[\SensitiveParameter]
        public string $plainPassword,
    ) {
    }
}
