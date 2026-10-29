<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\IssuePortalSession;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Service\PortalSessionIssuer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IssuePortalSessionHandler
{
    public function __construct(
        private PortalSessionIssuer $sessions,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(IssuePortalSession $command): PortalSessionTokens
    {
        return $this->sessions->open($command->userId, $this->clock->now());
    }
}
