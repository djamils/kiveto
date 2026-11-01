<?php

declare(strict_types=1);

namespace App\Context\Clinic\Infrastructure\Messaging\Consumer;

use App\Context\Clinic\Application\Port\ClinicReadRepositoryInterface;
use App\Context\Clinic\Domain\Event\ClinicActivated;
use App\Context\Clinic\Domain\Event\ClinicClosed;
use App\Context\Clinic\Domain\Event\ClinicCreated;
use App\Context\Clinic\Domain\Event\ClinicGroupActivated;
use App\Context\Clinic\Domain\Event\ClinicGroupSuspended;
use App\Context\Clinic\Domain\Event\ClinicLetterheadUpdated;
use App\Context\Clinic\Domain\Event\ClinicOpeningHoursUpdated;
use App\Context\Clinic\Domain\Event\ClinicProfileChangedIntegrationEvent;
use App\Context\Clinic\Domain\Event\ClinicRenamed;
use App\Context\Clinic\Domain\Event\ClinicSuspended;
use App\Context\Clinic\Domain\Event\ClinicTimeZoneChanged;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Shared\Domain\Event\DomainEventInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Announces, in the transaction of the change (outbox), every change of what
 * the clinic's public profile shows (S7c): one ClinicProfileChangedIntegrationEvent
 * per clinic. A clinic group suspended or reactivated announces each of its
 * member clinics, read through the clinic read repository.
 */
final readonly class ClinicProfileChangedIntegrationBridge
{
    public function __construct(
        private IntegrationEventPublisher $integrationEventPublisher,
        private ClinicReadRepositoryInterface $clinics,
    ) {
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onCreated(ClinicCreated $event): void
    {
        $this->announce($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onRenamed(ClinicRenamed $event): void
    {
        $this->announce($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onOpeningHoursUpdated(ClinicOpeningHoursUpdated $event): void
    {
        $this->announce($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onLetterheadUpdated(ClinicLetterheadUpdated $event): void
    {
        $this->announce($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onTimeZoneChanged(ClinicTimeZoneChanged $event): void
    {
        $this->announce($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onSuspended(ClinicSuspended $event): void
    {
        $this->announce($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onActivated(ClinicActivated $event): void
    {
        $this->announce($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onClosed(ClinicClosed $event): void
    {
        $this->announce($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onGroupSuspended(ClinicGroupSuspended $event): void
    {
        $this->announceMembersOf($event->aggregateId());
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onGroupActivated(ClinicGroupActivated $event): void
    {
        $this->announceMembersOf($event->aggregateId());
    }

    private function announce(DomainEventInterface $event): void
    {
        $this->integrationEventPublisher->publish(new ClinicProfileChangedIntegrationEvent($event->aggregateId()));
    }

    private function announceMembersOf(string $clinicGroupId): void
    {
        $events = [];

        foreach ($this->clinics->findAllFiltered(clinicGroupId: $clinicGroupId)->clinics as $clinic) {
            $events[] = new ClinicProfileChangedIntegrationEvent($clinic->id);
        }

        $this->integrationEventPublisher->publish(...$events);
    }
}
