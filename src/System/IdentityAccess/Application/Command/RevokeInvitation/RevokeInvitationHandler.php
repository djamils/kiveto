<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokeInvitation;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\Token\AccountTokenRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeInvitationHandler
{
    public function __construct(
        private AccountTokenRepositoryInterface $tokens,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeInvitation $command): int
    {
        return $this->tokens->invalidateInvitations($command->userId, $command->clinicId, $this->clock->now());
    }
}
