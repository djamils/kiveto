<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RecordUserLogin;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A session was opened: the team page reads it to tell who has started using
 * their account and when they were last seen.
 */
#[AsMessageHandler]
final readonly class RecordUserLoginHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RecordUserLogin $command): void
    {
        $this->userRepository->recordLogin(UserId::fromString($command->userId), $this->clock->now());
    }
}
