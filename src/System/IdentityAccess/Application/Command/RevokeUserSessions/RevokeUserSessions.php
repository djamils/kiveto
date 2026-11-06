<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokeUserSessions;

use App\Shared\Application\Bus\CommandInterface;

/**
 * « Révoquer » one session (`sessionRecordId`), or every session of the
 * account but the asking browser's own (`keepSessionId`). Answers how many
 * were revoked.
 */
final readonly class RevokeUserSessions implements CommandInterface
{
    public function __construct(
        public string $userId,
        public ?string $sessionRecordId = null,
        public ?string $keepSessionId = null,
    ) {
    }
}
