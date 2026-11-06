<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\EnableClinicAccount;

use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnableClinicAccountHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
    ) {
    }

    public function __invoke(EnableClinicAccount $command): void
    {
        $user = $this->users->findByIdForUpdate(UserId::fromString($command->userId))
            ?? throw new \RuntimeException(\sprintf('User "%s" not found.', $command->userId));

        $user->enableStaff();
        $this->users->save($user);
    }
}
