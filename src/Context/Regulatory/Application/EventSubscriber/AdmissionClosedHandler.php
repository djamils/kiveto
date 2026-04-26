<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Application\EventSubscriber;

use App\Context\Admission\Domain\Event\AdmissionClosedIntegrationEvent;
use App\Context\Admission\Domain\ValueObject\ClosureReason;
use App\Context\Regulatory\Domain\Repository\StrayCustodyRepositoryInterface;
use App\Shared\Application\Event\DomainEventPublisher;
use App\Shared\Domain\Time\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Closes a StrayCustody when an admission is closed with reason HandedToMunicipality.
 */
#[AsMessageHandler(bus: 'messenger.bus.integration_event')]
final readonly class AdmissionClosedHandler
{
    public function __construct(
        private StrayCustodyRepositoryInterface $strayCustodyRepo,
        private DomainEventPublisher $domainEventPublisher,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AdmissionClosedIntegrationEvent $event): void
    {
        if (ClosureReason::HandedToMunicipality->value !== $event->reason) {
            return;
        }

        $custody = $this->strayCustodyRepo->findByAdmissionId($event->admissionId);

        if (null === $custody) {
            return;
        }

        $custody->closeHandedToMunicipality($this->clock->now());
        $this->strayCustodyRepo->save($custody);
        $this->domainEventPublisher->publish($custody);
    }
}
