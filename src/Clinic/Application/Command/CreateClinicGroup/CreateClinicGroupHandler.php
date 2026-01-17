<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\CreateClinicGroup;

use App\Clinic\Domain\ClinicGroup;
use App\Clinic\Domain\Repository\ClinicGroupRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicGroupId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Identifier\UuidGeneratorInterface;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateClinicGroupHandler
{
    public function __construct(
        private readonly ClinicGroupRepositoryInterface $clinicGroupRepository,
        private readonly UuidGeneratorInterface $uuidGenerator,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(CreateClinicGroup $command): string
    {
        $clinicGroupId = ClinicGroupId::fromString($this->uuidGenerator->generate());
        $now           = $this->clock->now();

        $clinicGroup = ClinicGroup::create(
            $clinicGroupId,
            $command->name,
            $now,
        );

        $this->clinicGroupRepository->save($clinicGroup);

        $this->domainEventPublisher->publish($clinicGroup);

        return $clinicGroupId->toString();
    }
}
