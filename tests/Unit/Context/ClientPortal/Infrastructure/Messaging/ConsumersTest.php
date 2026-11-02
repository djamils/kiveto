<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Messaging;

use App\Context\Client\Domain\Event\ClientArchivedIntegrationEvent;
use App\Context\ClientPortal\Application\Command\ClosePortalRequestsForSubject\ClosePortalRequestsForSubject;
use App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess\RevokeClientPortalAccess;
use App\Context\ClientPortal\Application\Command\SyncBookingRequestFromAppointment\SyncBookingRequestFromAppointment;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmedIntegrationEvent;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\ClientArchivedIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\ClientLinkConfirmedIntegrationBridge;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\PendingAppointmentCancelledIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\PendingAppointmentConfirmedIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\PendingAppointmentDeclinedIntegrationEventConsumer;
use App\Context\Scheduling\Domain\Event\PendingAppointmentCancelledIntegrationEvent;
use App\Context\Scheduling\Domain\Event\PendingAppointmentConfirmedIntegrationEvent;
use App\Context\Scheduling\Domain\Event\PendingAppointmentDeclinedIntegrationEvent;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\IntegrationEventPublisher;
use App\Tests\Unit\Context\ClientPortal\Support\AnsweringCommandBus;
use PHPUnit\Framework\TestCase;

final class ConsumersTest extends TestCase
{
    public function testAnArchivedClientHasItsPortalAccessRevoked(): void
    {
        $bus = new class implements CommandBusInterface {
            /** @var list<object> */
            public array $commands = [];

            public function dispatch(object $command, object ...$stamps): mixed
            {
                $this->commands[] = $command;

                return null;
            }
        };

        new ClientArchivedIntegrationEventConsumer($bus)(new ClientArchivedIntegrationEvent('client-1', 'clinic-1'));

        [$close, $command] = $bus->commands;
        self::assertInstanceOf(RevokeClientPortalAccess::class, $command);
        self::assertSame(['clinic-1', 'client-1'], [$command->clinicId, $command->clientId]);
        // Its open requests are closed (AC 238)
        self::assertInstanceOf(ClosePortalRequestsForSubject::class, $close);
        self::assertSame([RequestSubject::CLIENT, 'client-1', ClosureReason::CLIENT_ARCHIVED, 'clinic-1'], [$close->subject, $close->subjectId, $close->reason, $close->clinicId]);
    }

    public function testAPendingBookingWithdrawnOrDeclinedClosesItsQuestionnaire(): void
    {
        $bus = new AnsweringCommandBus(static fn (object $command): int => 1);

        new PendingAppointmentCancelledIntegrationEventConsumer($bus)(new PendingAppointmentCancelledIntegrationEvent('rdv-1', 'clinic-1', 'client-1', 'pet-1', '2026-11-05 09:00:00'));
        new PendingAppointmentDeclinedIntegrationEventConsumer($bus)(new PendingAppointmentDeclinedIntegrationEvent('rdv-2', 'clinic-1', 'client-1', 'pet-1', '2026-11-05 09:00:00', 'expired'));

        self::assertSame(
            [
                [RequestSubject::APPOINTMENT, 'rdv-1', ClosureReason::APPOINTMENT_CANCELLED, 'clinic-1'],
                [RequestSubject::APPOINTMENT, 'rdv-2', ClosureReason::APPOINTMENT_DECLINED, 'clinic-1'],
            ],
            array_map(
                static fn (ClosePortalRequestsForSubject $command): array => [$command->subject, $command->subjectId, $command->reason, $command->clinicId],
                array_values(array_filter($bus->commands, static fn (object $command): bool => $command instanceof ClosePortalRequestsForSubject)),
            ),
        );
    }

    /** S7d: what became of a pending portal booking reaches the new client's request. */
    public function testThePendingBookingEventsSyncTheBookingRequests(): void
    {
        $bus = new AnsweringCommandBus(static fn (object $command): int => 1);

        new PendingAppointmentConfirmedIntegrationEventConsumer($bus)(new PendingAppointmentConfirmedIntegrationEvent('rdv-1', 'clinic-1', 'client-1', 'pet-1', '2026-11-05 09:00:00'));
        foreach (['declined', 'expired', 'clinic_cancelled'] as $reason) {
            new PendingAppointmentDeclinedIntegrationEventConsumer($bus)(new PendingAppointmentDeclinedIntegrationEvent('rdv-2', 'clinic-1', null, null, '2026-11-05 09:00:00', $reason));
        }
        new PendingAppointmentCancelledIntegrationEventConsumer($bus)(new PendingAppointmentCancelledIntegrationEvent('rdv-3', 'clinic-1', null, null, '2026-11-05 09:00:00'));

        self::assertSame(
            [['rdv-1', 'confirmed', 'client-1'], ['rdv-2', 'declined', null], ['rdv-2', 'expired', null], ['rdv-2', 'cancelled', null], ['rdv-3', 'cancelled', null]],
            array_map(
                static fn (SyncBookingRequestFromAppointment $command): array => [$command->appointmentId, $command->outcome, $command->ownerId],
                array_values(array_filter($bus->commands, static fn (object $command): bool => $command instanceof SyncBookingRequestFromAppointment)),
            ),
        );
    }

    public function testAConfirmedLinkIsAnnouncedToOtherContexts(): void
    {
        $bus = new class implements IntegrationEventBusInterface {
            /** @var list<object> */
            public array $events = [];

            public function publish(array $stamps, object ...$events): void
            {
                foreach ($events as $event) {
                    $this->events[] = $event;
                }
            }
        };

        new ClientLinkConfirmedIntegrationBridge(new IntegrationEventPublisher($bus))(new ClientLinkConfirmed('link-1', 'user-1', 'clinic-1', 'client-1'));

        [$event] = $bus->events;
        self::assertInstanceOf(ClientLinkConfirmedIntegrationEvent::class, $event);
        self::assertSame(['link-1', 'user-1', 'clinic-1', 'client-1'], [$event->linkId, $event->userId, $event->clinicId, $event->clientId]);
    }
}
