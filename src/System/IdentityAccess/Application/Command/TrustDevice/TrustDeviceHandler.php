<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Application\Command\TrustDevice;

use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Security\OpaqueTokenHasherInterface;
use App\Shared\Domain\Time\ClockInterface;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDevice;
use App\System\IdentityAccess\Domain\SecondFactor\TrustedDeviceRepositoryInterface;
use App\System\IdentityAccess\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TrustDeviceHandler
{
    public function __construct(
        private TrustedDeviceRepositoryInterface $devices,
        private OpaqueTokenHasherInterface $tokenHasher,
        private UuidGeneratorInterface $uuids,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(TrustDevice $command): string
    {
        $secret = $this->tokenHasher->newSecret();

        $this->devices->add(TrustedDevice::trust(
            $this->uuids->generate(),
            UserId::fromString($command->userId),
            $this->tokenHasher->hash($secret),
            $command->label,
            $this->clock->now(),
        ));

        return $secret;
    }
}
