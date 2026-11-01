<?php

declare(strict_types=1);

namespace App\Context\Scheduling\Infrastructure\Messaging\Consumer;

use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Event\PendingAppointmentCancelledIntegrationEvent;
use App\Context\Scheduling\Domain\Event\PendingAppointmentConfirmedIntegrationEvent;
use App\Context\Scheduling\Domain\Event\PendingAppointmentDeclinedIntegrationEvent;
use App\Context\Scheduling\Domain\ValueObject\AppointmentSource;
use App\Shared\Application\Event\IntegrationEventPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The only integration events of Scheduling (S4j, README D1): what became of
 * a pending portal booking — confirmed (also silently, at the desk),
 * declined (button, expiry, clinic cancel) or withdrawn by the client —
 * announced in the transaction of the change (outbox). An appointment booked
 * at the clinic, or one that was never pending, announces nothing.
 */
final readonly class PendingAppointmentIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher)
    {
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onConfirmed(AppointmentConfirmed $event): void
    {
        if (!self::isPortal($event->source())) {
            return;
        }

        $this->integrationEventPublisher->publish(new PendingAppointmentConfirmedIntegrationEvent(
            appointmentId: $event->appointmentId(),
            clinicId: $event->clinicId(),
            ownerId: $event->ownerId(),
            animalId: $event->animalId(),
            startsAtUtc: $event->startsAtUtc(),
        ));
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onDeclined(AppointmentDeclined $event): void
    {
        if (!self::isPortal($event->source())) {
            return;
        }

        $this->integrationEventPublisher->publish(new PendingAppointmentDeclinedIntegrationEvent(
            appointmentId: $event->appointmentId(),
            clinicId: $event->clinicId(),
            ownerId: $event->ownerId(),
            animalId: $event->animalId(),
            startsAtUtc: $event->startsAtUtc(),
            reason: $event->reason(),
        ));
    }

    #[AsMessageHandler(bus: 'messenger.bus.event')]
    public function onCancelled(AppointmentCancelled $event): void
    {
        if (!$event->wasPending() || !self::isPortal($event->source())) {
            return;
        }

        $this->integrationEventPublisher->publish(new PendingAppointmentCancelledIntegrationEvent(
            appointmentId: $event->appointmentId(),
            clinicId: $event->clinicId(),
            ownerId: $event->ownerId(),
            animalId: $event->animalId(),
            startsAtUtc: (string) $event->startsAtUtc(),
        ));
    }

    private static function isPortal(string $source): bool
    {
        return AppointmentSource::PORTAL->value === $source;
    }
}
