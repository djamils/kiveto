<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\DisablePortalUser;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Exception\PortalAccountUnavailableException;
use App\System\IdentityAccess\Application\Service\PortalOwnerAccounts;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\Session\RefreshTokenRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DisablePortalUserHandler
{
    public function __construct(
        private PortalOwnerAccounts $owners,
        private UserRepositoryInterface $users,
        private RefreshTokenRepositoryInterface $refreshTokens,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws PortalAccountUnavailableException not an ACTIVE owner account (already disabled, for one)
     */
    public function __invoke(DisablePortalUser $command): void
    {
        $user = $this->owners->active($command->userId, lock: true);

        $user->disablePortal();
        $this->users->save($user);

        $this->refreshTokens->revokeAllForUser($user->id()->toString(), $this->clock->now());
    }
}
