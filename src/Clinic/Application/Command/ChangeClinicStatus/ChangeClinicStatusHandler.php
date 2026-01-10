<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ChangeClinicStatus;

use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicStatus;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\DependencyInjection\DomainEventPublisherAware;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChangeClinicStatusHandler
{
    use DomainEventPublisherAware;

    public function __construct(
        private readonly ClinicRepositoryInterface $clinicRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeClinicStatus $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clinic   = $this->clinicRepository->findById($clinicId);

        if (null === $clinic) {
            throw new \RuntimeException(\sprintf('Clinic with ID "%s" not found.', $command->clinicId));
        }

        $now = $this->clock->now();

        match ($command->status) {
            ClinicStatus::ACTIVE    => $clinic->activate($now),
            ClinicStatus::SUSPENDED => $clinic->suspend($now),
            ClinicStatus::CLOSED    => $clinic->close($now),
        };

        $this->clinicRepository->save($clinic);

        $this->domainEventPublisher->publish($clinic);
    }
}
