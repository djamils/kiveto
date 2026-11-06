<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\EndUserSession;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EndUserSessionHandler
{
    public function __construct(
        private UserSessionStoreInterface $sessions,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(EndUserSession $command): void
    {
        $this->sessions->end(hash('sha256', $command->sessionId), $this->clock->now());
    }
}
