<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\ListUserSessions;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ListUserSessionsHandler
{
    public const int DAYS = 30;

    public function __construct(
        private UserSessionStoreInterface $sessions,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<UserSessionView>
     */
    public function __invoke(ListUserSessions $query): array
    {
        return $this->sessions->listActive(
            $query->userId,
            $this->clock->now()->modify(\sprintf('-%d days', self::DAYS)),
            null !== $query->currentSessionId ? hash('sha256', $query->currentSessionId) : null,
        );
    }
}
