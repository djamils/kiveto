<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\IssuePortalSession;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A new session (token pair, new family) for an owner whose phone was just
 * proven. Answers PortalSessionTokens.
 */
final readonly class IssuePortalSession implements CommandInterface
{
    public function __construct(
        public string $userId,
    ) {
    }
}
