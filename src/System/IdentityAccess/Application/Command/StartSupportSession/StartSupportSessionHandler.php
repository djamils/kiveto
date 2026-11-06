<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\StartSupportSession;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\AccountLinkBuilderInterface;
use App\System\IdentityAccess\Domain\Support\SupportMode;
use App\System\IdentityAccess\Domain\Support\SupportSession;
use App\System\IdentityAccess\Domain\Support\SupportSessionRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class StartSupportSessionHandler
{
    public function __construct(
        private SupportSessionRepositoryInterface $sessions,
        private UuidGeneratorInterface $uuids,
        private OpaqueTokenHasherInterface $tokenHasher,
        private AccountLinkBuilderInterface $links,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \InvalidArgumentException no reason, an unknown mode or duration
     */
    public function __invoke(StartSupportSession $command): SupportHandoff
    {
        $mode = SupportMode::tryFrom($command->mode) ?? throw new \InvalidArgumentException('Mode de session inconnu.');

        $secret  = $this->tokenHasher->newSecret();
        $session = SupportSession::start(
            id: $this->uuids->generate(),
            clinicId: $command->clinicId,
            userId: $command->userId,
            adminId: $command->adminId,
            adminName: $command->adminName,
            reason: $command->reason,
            mode: $mode,
            minutes: $command->minutes,
            handoffHash: $this->tokenHasher->hash($secret),
            at: $this->clock->now(),
        );
        $this->sessions->save($session);

        return new SupportHandoff($session->id(), $this->links->supportHandoffUrl($secret));
    }
}
