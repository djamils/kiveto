<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\DisableClinicAccount;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\UserSessionStoreInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DisableClinicAccountHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UserSessionStoreInterface $sessions,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DisableClinicAccount $command): int
    {
        $user = $this->users->findByIdForUpdate(UserId::fromString($command->userId))
            ?? throw new \RuntimeException(\sprintf('User "%s" not found.', $command->userId));

        $user->disableStaff();
        $this->users->save($user);

        return $this->sessions->revokeAll($command->userId, null, $this->clock->now());
    }
}
