<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ClaimSupportSession;

use App\Shared\Application\Bus\CommandInterface;

/**
 * The clinic host opens a support session from its hand-off secret: once,
 * within two minutes. Answers the session, or null for a secret unknown,
 * used or too old.
 */
final readonly class ClaimSupportSession implements CommandInterface
{
    public function __construct(
        public string $secret,
    ) {
    }
}
