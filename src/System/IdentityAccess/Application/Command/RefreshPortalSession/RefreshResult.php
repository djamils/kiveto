<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RefreshPortalSession;

use App\System\IdentityAccess\Application\Command\IssuePortalSession\PortalSessionTokens;
use App\System\IdentityAccess\Domain\Session\RefreshOutcome;

final readonly class RefreshResult
{
    /**
     * @param PortalSessionTokens|null $tokens the new pair, when the outcome issues one
     */
    public function __construct(
        public RefreshOutcome $outcome,
        public ?PortalSessionTokens $tokens = null,
    ) {
    }
}
