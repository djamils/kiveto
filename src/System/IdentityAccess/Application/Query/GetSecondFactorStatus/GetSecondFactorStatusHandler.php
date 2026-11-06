<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\GetSecondFactorStatus;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactor;
use App\System\IdentityAccess\Domain\SecondFactor\SecondFactorRepositoryInterface;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetSecondFactorStatusHandler
{
    public function __construct(
        private SecondFactorRepositoryInterface $factors,
        private TrustedDeviceRepositoryInterface $devices,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(GetSecondFactorStatus $query): SecondFactorStatus
    {
        $userId = UserId::fromString($query->userId);
        $factor = $this->factors->findByUserId($userId);

        return new SecondFactorStatus(
            enabled: null !== $factor,
            enabledAt: $factor?->enabledAt(),
            remainingBackupCodes: $factor?->remainingBackupCodes() ?? 0,
            backupCodeCount: SecondFactor::BACKUP_CODE_COUNT,
            trustedDevices: $this->devices->countUsable($userId, $this->clock->now()),
        );
    }
}
