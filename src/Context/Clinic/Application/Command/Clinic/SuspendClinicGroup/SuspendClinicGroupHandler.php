<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\SuspendClinicGroup;

use App\Context\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Shared\Application\Event\DomainEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SuspendClinicGroupHandler
{
    public function __construct(
        private readonly ClinicGroupRepositoryInterface $clinicGroupRepository,
        private readonly DomainEventPublisher $domainEventPublisher,
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
