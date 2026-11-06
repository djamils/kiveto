<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\ClaimSupportSession;

use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Query\SupportSessionView;
use App\System\IdentityAccess\Domain\Support\SupportSessionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ClaimSupportSessionHandler
{
    public function __construct(
        private SupportSessionRepositoryInterface $sessions,
        private OpaqueTokenHasherInterface $tokenHasher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ClaimSupportSession $command): ?SupportSessionView
    {
        $session = $this->sessions->findByHandoffHashForUpdate($this->tokenHasher->hash($command->secret));

        if (null === $session || !$session->claim($this->clock->now())) {
            return null;
        }

        $this->sessions->save($session);

        return SupportSessionView::of($session);
    }
}
