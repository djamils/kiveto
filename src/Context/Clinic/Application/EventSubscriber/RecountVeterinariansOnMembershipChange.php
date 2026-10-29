<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\EventSubscriber;

use App\Context\Clinic\Application\Command\Staff\RecordVeterinarianDailyCounts\RecordVeterinarianDailyCounts;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipCreated;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipDisabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipEnabled;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipRoleChanged;
use App\Context\Clinic\Domain\Staff\Event\ClinicMembershipValidityChanged;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Event\DomainEventInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * A membership that starts, stops, changes role or changes its window may
 * change the clinic's number of active veterinarians: today's count is
 * taken again (it only ever goes up within a day, D6).
 */
final readonly class RecountVeterinariansOnMembershipChange
{
    public function __construct(private CommandBusInterface $commandBus)
    {
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onCreated(ClinicMembershipCreated $event): void
    {
        $this->recount($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onEnabled(ClinicMembershipEnabled $event): void
    {
        $this->recount($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onDisabled(ClinicMembershipDisabled $event): void
    {
        $this->recount($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onRoleChanged(ClinicMembershipRoleChanged $event): void
    {
        $this->recount($event);
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onValidityChanged(ClinicMembershipValidityChanged $event): void
    {
        $this->recount($event);
    }

    private function recount(DomainEventInterface $event): void
    {
        $clinicId = $event->payload()['clinicId'] ?? null;
        \assert(\is_string($clinicId));

        $this->commandBus->dispatch(new RecordVeterinarianDailyCounts($clinicId));
    }
}
