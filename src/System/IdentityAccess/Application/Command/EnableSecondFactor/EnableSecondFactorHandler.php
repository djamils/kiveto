<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\EnableSecondFactor;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Application\Service\BackupCodes;
use App\System\IdentityAccess\Domain\Repository\UserRepositoryInterface;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactorRepositoryInterface;
use App\System\IdentityAccess\Domain\SecondFactor\TotpSecret;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use App\System\IdentityAccess\Domain\ValueObject\UserType;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class EnableSecondFactorHandler
{
    public function __construct(
        private UserRepositoryInterface $users,
        private SecondFactorRepositoryInterface $factors,
        private BackupCodes $backupCodes,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<string>
     */
    public function __invoke(EnableSecondFactor $command): array
    {
        $userId = UserId::fromString($command->userId);
        $user   = $this->users->findById($userId);

        if (null === $user || UserType::BACKOFFICE !== $user->type()) {
            throw new \LogicException('Only back-office accounts have a second factor.');
        }

        $codes = $this->backupCodes->fresh();

        $this->factors->save(SecondFactor::enable(
            $userId,
            TotpSecret::fromBase32($command->secret),
            $command->code,
            $codes['hashes'],
            $this->clock->now(),
        ));

        return $codes['plain'];
    }
}
