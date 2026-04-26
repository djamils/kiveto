<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Application\EventSubscriber;

use App\Context\Patient\Domain\Event\PatientLinkedToAnimalIntegrationEvent;
use App\Context\Regulatory\Domain\Repository\StrayCustodyRepositoryInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Cancels an active StrayCustody when the patient is linked to a known animal (owner found).
 */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class PatientLinkedToAnimalHandler
{
    public function __construct(
        private StrayCustodyRepositoryInterface $strayCustodyRepo,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PatientLinkedToAnimalIntegrationEvent $event): void
    {
        $custody = $this->strayCustodyRepo->findActiveByPatientId($event->patientId);

        if (null === $custody) {
            return;
        }

        $custody->cancelOwnerFound($this->clock->now());
        $this->strayCustodyRepo->save($custody);
        $this->domainEventPublisher->publish($custody);
    }
}
