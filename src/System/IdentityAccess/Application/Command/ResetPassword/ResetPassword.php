<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ResetPassword;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A new password chosen from a reset link. An unusable link throws
 * InvalidAccountTokenException, a refused password UnacceptablePasswordException.
 */
final readonly class ResetPassword implements CommandInterface
{
    public function __construct(
        public string $token,
        #[\SensitiveParameter]
        public string $plainPassword,
    ) {
    }
}
