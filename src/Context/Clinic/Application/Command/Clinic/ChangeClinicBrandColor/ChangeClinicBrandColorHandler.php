<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\ChangeClinicBrandColor;

use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\Staff\ValueObject\HexColor;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ChangeClinicBrandColorHandler
{
    public function __construct(
        private ClinicRepositoryInterface $clinicRepository,
        private ClockInterface $clock,
        private DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(ChangeClinicBrandColor $command): void
    {
        $clinic = $this->clinicRepository->findById(ClinicId::fromString($command->clinicId));

        if (null === $clinic) {
            throw new \RuntimeException(\sprintf('Clinic with ID "%s" not found.', $command->clinicId));
        }

        $color = null !== $command->brandColor && '' !== mb_trim($command->brandColor)
            ? HexColor::fromString($command->brandColor)
            : null;

        $clinic->changeBrandColor($color, $this->clock->now());

        $this->clinicRepository->save($clinic);

        $this->domainEventPublisher->publish($clinic);
    }
}
