<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokeUserSessions;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeUserSessionsHandler
{
    public function __construct(
        private UserSessionStoreInterface $sessions,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeUserSessions $command): int
    {
        $now = $this->clock->now();

        if (null !== $command->sessionRecordId) {
            return $this->sessions->revoke($command->userId, $command->sessionRecordId, $now) ? 1 : 0;
        }

        return $this->sessions->revokeAll(
            $command->userId,
            null !== $command->keepSessionId ? hash('sha256', $command->keepSessionId) : null,
            $now,
        );
    }
}
