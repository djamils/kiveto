<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ChangeClinicSlug;

use App\Clinic\Application\Exception\DuplicateClinicSlugException;
use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Clinic\Domain\ValueObject\ClinicSlug;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChangeClinicSlugHandler
{
    public function __construct(
        private readonly ClinicRepositoryInterface $clinicRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ChangeClinicSlug $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clinic   = $this->clinicRepository->findById($clinicId);

        if (null === $clinic) {
            throw new \RuntimeException(\sprintf('Clinic with ID "%s" not found.', $command->clinicId));
        }

        $newSlug = ClinicSlug::fromString($command->slug);

        if (!$newSlug->equals($clinic->slug()) && $this->clinicRepository->existsBySlug($newSlug)) {
            throw new DuplicateClinicSlugException($command->slug);
        }

        $clinic->changeSlug($newSlug, $this->clock->now());

        $this->clinicRepository->save($clinic);

        $this->domainEventPublisher->publish($clinic);
    }
}
