<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\TrackUserSession;

use App\Shared\Application\Bus\CommandInterface;

/**
 * A request of a signed-in staff or back-office browser: its session is
 * listed (opened, or seen again at most once a minute). Answers false when
 * the session was revoked — the caller ends it.
 */
final readonly class TrackUserSession implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $sessionId,
        public ?string $userAgent,
        public ?string $ipAddress,
    ) {
    }
}
