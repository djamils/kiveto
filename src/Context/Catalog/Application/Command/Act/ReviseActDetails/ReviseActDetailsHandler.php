<?php

declare(strict_types=1);

namespace App\Context\Catalog\Application\Command\Act\ReviseActDetails;

use App\Context\Catalog\Domain\Act\Exception\ActNotFoundException;
use App\Context\Catalog\Domain\Act\Repository\ActRepositoryInterface;
use App\Context\Catalog\Domain\Act\ValueObject\ActCategory;
use App\Context\Catalog\Domain\Act\ValueObject\ActDescription;
use App\Context\Catalog\Domain\Act\ValueObject\ActDuration;
use App\Context\Catalog\Domain\Act\ValueObject\ActId;
use App\Context\Catalog\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ReviseActDetailsHandler
{
    public function __construct(
        private ActRepositoryInterface $actRepository,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ReviseActDetails $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $act      = $this->actRepository->findById(ActId::fromString($command->actId), $clinicId);

        if (null === $act) {
            throw new ActNotFoundException($command->actId);
        }

        $act->reviseDetails(
            ActDescription::fromNullableString($command->description),
            ActCategory::from($command->category),
            ActDuration::ofMinutes($command->estimatedDurationMinutes),
            $command->requiresAnesthesia,
            $this->clock->now(),
        );

        $this->actRepository->save($act);
        $this->domainEventPublisher->publish($act);
    }
}
