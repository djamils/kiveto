<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListUserSessions;

use App\Shared\Application\Bus\QueryInterface;

/**
 * « Sessions actives » of an account: seen within the last 30 days, the
 * asking browser's own (`currentSessionId`) flagged.
 */
final readonly class ListUserSessions implements QueryInterface
{
    public function __construct(
        public string $userId,
        public ?string $currentSessionId = null,
    ) {
    }
}
