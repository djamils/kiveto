<?php

declare(strict_types=1);

namespace App\Clinic\Application\Command\ChangeClinicLocale;

use App\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Clinic\Domain\ValueObject\ClinicId;
use App\Shared\Domain\Localization\Locale;
use App\Shared\Domain\Time\ClockInterface;
use App\Shared\Infrastructure\DependencyInjection\DomainEventPublisherAware;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ChangeClinicLocaleHandler
{
    use DomainEventPublisherAware;

    public function __construct(
        private readonly ClinicRepositoryInterface $clinicRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangeClinicLocale $command): void
    {
        $clinicId = ClinicId::fromString($command->clinicId);
        $clinic   = $this->clinicRepository->findById($clinicId);

        if (null === $clinic) {
            throw new \RuntimeException(\sprintf('Clinic with ID "%s" not found.', $command->clinicId));
        }

        $clinic->changeLocale(
            Locale::fromString($command->locale),
            $this->clock->now(),
        );

        $this->clinicRepository->save($clinic);

        $this->domainEventPublisher->publish($clinic);
    }
}
