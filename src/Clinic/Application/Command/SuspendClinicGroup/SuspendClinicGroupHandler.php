<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\SuspendClinicGroup;

use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Shared\Infrastructure\DependencyInjection\DomainEventPublisherAware;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SuspendClinicGroupHandler
{
    use DomainEventPublisherAware;

    public function __construct(
        private readonly ClinicGroupRepositoryInterface $clinicGroupRepository,
    ) {
    }

    public function __invoke(SuspendClinicGroup $command): void
    {
        $clinicGroupId = ClinicGroupId::fromString($command->clinicGroupId);
        $clinicGroup   = $this->clinicGroupRepository->findById($clinicGroupId);

        if (null === $clinicGroup) {
            throw new \RuntimeException(\sprintf('Clinic group with ID "%s" not found.', $command->clinicGroupId));
        }

        $clinicGroup->suspend();

        $this->clinicGroupRepository->save($clinicGroup);

        $this->domainEventPublisher->publish($clinicGroup);
    }
}
