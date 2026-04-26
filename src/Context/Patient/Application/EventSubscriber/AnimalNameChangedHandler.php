<?php

declare(strict_types=1);

namespace App\Context\Patient\Application\EventSubscriber;

use App\Context\Animal\Domain\Event\AnimalNameChangedIntegrationEvent;
use App\Context\Patient\Domain\Repository\PatientRepositoryInterface;
use App\Context\Patient\Domain\ValueObject\ClinicId;
use App\Context\Patient\Domain\ValueObject\DisplayLabel;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class AnimalNameChangedHandler
{
    public function __construct(
        private PatientRepositoryInterface $patientRepository,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AnimalNameChangedIntegrationEvent $event): void
    {
        $clinicId = ClinicId::fromString($event->clinicId);
        $now      = $this->clock->now();
        $newLabel = DisplayLabel::fromAnimal($event->newName);

        $patients = $this->patientRepository->findActiveByAnimalId($clinicId, $event->animalId);

        foreach ($patients as $patient) {
            // updateDisplayLabel is a no-op if kind === Custom (aggregate handles this)
            $patient->updateDisplayLabel($newLabel, $now);
            $this->patientRepository->save($patient);
            $this->domainEventPublisher->publish($patient);
        }
    }
}
