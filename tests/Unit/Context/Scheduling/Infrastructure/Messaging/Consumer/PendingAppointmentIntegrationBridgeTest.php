<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Messaging\Consumer;

use App\Context\Scheduling\Domain\Event\AppointmentCancelled;
use App\Context\Scheduling\Domain\Event\AppointmentConfirmed;
use App\Context\Scheduling\Domain\Event\AppointmentDeclined;
use App\Context\Scheduling\Domain\Event\PendingAppointmentCancelledIntegrationEvent;
use App\Context\Scheduling\Domain\Event\PendingAppointmentConfirmedIntegrationEvent;
use App\Context\Scheduling\Domain\Event\PendingAppointmentDeclinedIntegrationEvent;
use App\Context\Scheduling\Infrastructure\Messaging\Consumer\PendingAppointmentIntegrationBridge;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Tests\Unit\Context\Scheduling\Support\Appointments;
use PHPUnit\Framework\TestCase;

/**
 * AC 146: one integration event per transition of a pending portal booking;
 * a clinic booking, or an appointment that was never pending, publishes none.
 */
final class PendingAppointmentIntegrationBridgeTest extends TestCase
{
    private const string AT = '2026-10-22T07:30:00+00:00';

    /** @var list<object> */
    private array $published = [];

    public function testAConfirmedPortalRequestIsAnnounced(): void
    {
        $bridge = $this->bridge();

        // Silently (check-in) or not, a portal request confirmed is announced; a clinic one is not
        $bridge->onConfirmed(new AppointmentConfirmed(Appointments::ID, Appointments::CLINIC, Appointments::ROUSSEAU, self::AT, Appointments::OWNER, Appointments::ANIMAL, 'portal', true));
        $bridge->onConfirmed(new AppointmentConfirmed(Appointments::ID, Appointments::CLINIC, Appointments::ROUSSEAU, self::AT, Appointments::OWNER, Appointments::ANIMAL, 'clinic', false));

        self::assertCount(1, $this->published);
        $event = $this->published[0];
        self::assertInstanceOf(PendingAppointmentConfirmedIntegrationEvent::class, $event);
        self::assertSame(Appointments::ID, $event->aggregateId());
        self::assertSame('scheduling.pending-appointment-confirmed-integration.event.v1', $event->name());
        self::assertSame([
            'appointmentId' => Appointments::ID,
            'clinicId'      => Appointments::CLINIC,
            'ownerId'       => Appointments::OWNER,
            'animalId'      => Appointments::ANIMAL,
            'startsAtUtc'   => self::AT,
        ], $event->payload());
    }

    public function testADeclinedPortalRequestIsAnnouncedWithItsReason(): void
    {
        $bridge = $this->bridge();

        // A new client's request has no owner yet
        $bridge->onDeclined(new AppointmentDeclined(Appointments::ID, Appointments::CLINIC, Appointments::ROUSSEAU, self::AT, null, Appointments::ANIMAL, 'portal', 'expired'));
        $bridge->onDeclined(new AppointmentDeclined(Appointments::ID, Appointments::CLINIC, Appointments::ROUSSEAU, self::AT, null, Appointments::ANIMAL, 'clinic', 'declined'));

        self::assertCount(1, $this->published);
        $event = $this->published[0];
        self::assertInstanceOf(PendingAppointmentDeclinedIntegrationEvent::class, $event);
        self::assertSame(Appointments::ID, $event->aggregateId());
        self::assertSame([
            'appointmentId' => Appointments::ID,
            'clinicId'      => Appointments::CLINIC,
            'ownerId'       => null,
            'animalId'      => Appointments::ANIMAL,
            'startsAtUtc'   => self::AT,
            'reason'        => 'expired',
        ], $event->payload());
    }

    public function testOnlyAPendingPortalRequestWithdrawnByTheClientIsAnnounced(): void
    {
        $bridge = $this->bridge();

        $bridge->onCancelled(new AppointmentCancelled(Appointments::ID, Appointments::CLINIC, true, 'client', 'no_longer_needed', true, 'portal', Appointments::OWNER, Appointments::ANIMAL, self::AT));
        // A confirmed portal booking cancelled, a clinic one: nothing
        $bridge->onCancelled(new AppointmentCancelled(Appointments::ID, Appointments::CLINIC, true, 'client', null, false, 'portal', Appointments::OWNER, Appointments::ANIMAL, self::AT));
        $bridge->onCancelled(new AppointmentCancelled(Appointments::ID, Appointments::CLINIC, true, 'clinic', null, true, 'clinic', Appointments::OWNER, Appointments::ANIMAL, self::AT));

        self::assertCount(1, $this->published);
        $event = $this->published[0];
        self::assertInstanceOf(PendingAppointmentCancelledIntegrationEvent::class, $event);
        self::assertSame(Appointments::ID, $event->aggregateId());
        self::assertSame([
            'appointmentId' => Appointments::ID,
            'clinicId'      => Appointments::CLINIC,
            'ownerId'       => Appointments::OWNER,
            'animalId'      => Appointments::ANIMAL,
            'startsAtUtc'   => self::AT,
        ], $event->payload());
    }

    private function bridge(): PendingAppointmentIntegrationBridge
    {
        $bus = $this->createStub(IntegrationEventBusInterface::class);
        $bus->method('publish')->willReturnCallback(function (array $stamps, object ...$events): void {
            $this->published = [...$this->published, ...array_values($events)];
        });

        return new PendingAppointmentIntegrationBridge(new IntegrationEventPublisher($bus));
    }
}
