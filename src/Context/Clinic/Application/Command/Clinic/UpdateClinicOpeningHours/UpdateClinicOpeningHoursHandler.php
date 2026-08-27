<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Command\Clinic\UpdateClinicOpeningHours;

use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicId;
use App\Context\Clinic\Domain\ValueObject\ExceptionalClosure;
use App\Context\Clinic\Domain\ValueObject\OpeningHours;
use App\Context\Clinic\Domain\ValueObject\OpeningSlot;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateClinicOpeningHoursHandler
{
    public function __construct(
        private readonly ClinicRepositoryInterface $clinicRepository,
        private readonly ClockInterface $clock,
        private readonly DomainEventPublisher $domainEventPublisher,
    ) {
    }

    public function __invoke(UpdateClinicOpeningHours $command): void
    {
        $clinic = $this->clinicRepository->findById(ClinicId::fromString($command->clinicId));

        if (null === $clinic) {
            throw new \RuntimeException(\sprintf('Clinic with ID "%s" not found.', $command->clinicId));
        }

        $week = [];

        foreach ($command->week as $day => $slots) {
            foreach ($slots as $slot) {
                $week[$day][] = OpeningSlot::create($slot['opensAt'], $slot['closesAt']);
            }
        }

        $closures = [];

        foreach ($command->closures as $closure) {
            $closures[] = ExceptionalClosure::create($closure['date'], $closure['reason'] ?? null);
        }

        $clinic->updateOpeningHours(OpeningHours::create($week, $closures), $this->clock->now());

        $this->clinicRepository->save($clinic);

        $this->domainEventPublisher->publish($clinic);
    }
}
