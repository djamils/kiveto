<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Package\RemovePackageComponent;

use App\Context\Catalog\Domain\Package\Exception\PackageNotFoundException;
use App\Context\Catalog\Domain\Package\Repository\PackageRepositoryInterface;
use App\Context\Catalog\Domain\Package\ValueObject\PackageComponentId;
use App\Context\Catalog\Domain\Package\ValueObject\PackageId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RemovePackageComponentHandler
{
    public function __construct(
        private readonly PackageRepositoryInterface $packageRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(RemovePackageComponent $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $package  = $this->packageRepository->findById(PackageId::fromString($command->packageId), $clinicId);

        if (null === $package) {
            throw new PackageNotFoundException($command->packageId);
        }

        $package->removeComponent(PackageComponentId::fromString($command->componentId), $this->clock->now());

        $this->packageRepository->save($package);
        $this->domainEventPublisher->publish($package);
    }
}
