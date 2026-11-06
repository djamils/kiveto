<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RecordSupportPage;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\Support\SupportSessionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordSupportPageHandler
{
    public function __construct(
        private SupportSessionRepositoryInterface $sessions,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordSupportPage $command): bool
    {
        $session = $this->sessions->find($command->sessionId);

        if (null === $session || !$session->isOpenAt($this->clock->now())) {
            return false;
        }

        $session->recordPage($command->write);
        $this->sessions->save($session);

        return true;
    }
}
