<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\AnonymisePortalUser;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AnonymisePortalUserHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws \LogicException unknown account, or not a disabled owner account
     */
    public function __invoke(AnonymisePortalUser $command): void
    {
        $user = $this->users->findByIdForUpdate(UserId::fromString($command->userId))
            ?? throw new \LogicException('Unknown owner account.');
        $phone = $user->phone();

        $user->anonymisePortal($this->clock->now());
        $this->users->save($user);

        if (null !== $phone) {
            $this->users->deletePortalPhoneLock($phone);
        }
    }
}
