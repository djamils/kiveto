<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokePortalSession;

use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\Session\RefreshTokenRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokePortalSessionHandler
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokens,
        private OpaqueTokenHasherInterface $tokenHasher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokePortalSession $command): void
    {
        $token = $this->refreshTokens->findByHashForUpdate($this->tokenHasher->hash($command->refreshToken));

        if (null !== $token && $token->userId() === $command->userId) {
            $this->refreshTokens->revokeFamily($token->familyId(), $this->clock->now());
        }
    }
}
