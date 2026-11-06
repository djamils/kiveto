<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\RevokeTrustedDevices;

use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RevokeTrustedDevicesHandler
{
    public function __construct(
        private TrustedDeviceRepositoryInterface $devices,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RevokeTrustedDevices $command): int
    {
        return $this->devices->revokeAll(UserId::fromString($command->userId), $this->clock->now());
    }
}
