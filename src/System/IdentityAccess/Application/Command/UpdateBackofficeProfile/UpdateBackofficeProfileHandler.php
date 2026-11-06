<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\UpdateBackofficeProfile;

use App\Shared\Domain\Localization\TimeZone;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Port\BackofficeProfileStoreInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateBackofficeProfileHandler
{
    private const int MAX_NAME_LENGTH = 80;

    public function __construct(
        private UserRepositoryInterface $users,
        private BackofficeProfileStoreInterface $profiles,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(UpdateBackofficeProfile $command): void
    {
        $user = $this->users->findById(UserId::fromString($command->userId));

        if (null === $user || UserType::BACKOFFICE !== $user->type()) {
            throw new \LogicException('Only back-office accounts have a console profile.');
        }

        $name = trim($command->displayName);

        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Le nom affiché compte de 1 à %d caractères.', self::MAX_NAME_LENGTH));
        }

        $this->profiles->save(
            $command->userId,
            $name,
            TimeZone::fromString($command->timeZone)->toString(),
            $command->notifyOnRegistryFailure,
            $command->notifyOnInvitationExpiry,
            $this->clock->now(),
        );
    }
}
