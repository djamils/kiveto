<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Query\IsDeviceTrusted;

use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class IsDeviceTrustedHandler
{
    public function __construct(
        private TrustedDeviceRepositoryInterface $devices,
        private OpaqueTokenHasherInterface $tokenHasher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(IsDeviceTrusted $query): bool
    {
        if ('' === $query->secret) {
            return false;
        }

        $device = $this->devices->findByTokenHash($this->tokenHasher->hash($query->secret));

        return null !== $device
            && $device->userId()->toString() === $query->userId
            && $device->isUsableAt($this->clock->now());
    }
}
