<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\EndSupportSession;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Terminer la session », or its time ran out. Answers the session as it
 * ended (`ended` false when it had ended already), null when unknown.
 */
final readonly class EndSupportSession implements CommandInterface
{
    public function __construct(
        public string $sessionId,
    ) {
    }
}
