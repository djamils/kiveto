<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Messaging;

use App\Context\Client\Domain\Event\ClientArchivedIntegrationEvent;
use App\Context\ClientPortal\Application\Command\ClosePortalRequestsForSubject\ClosePortalRequestsForSubject;
use App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess\RevokeClientPortalAccess;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmedIntegrationEvent;
use App\Context\ClientPortal\Domain\Request\ClosureReason;
use App\Context\ClientPortal\Domain\Request\RequestSubject;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\ClientArchivedIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\ClientLinkConfirmedIntegrationBridge;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\PendingAppointmentCancelledIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\PendingAppointmentDeclinedIntegrationEventConsumer;
use App\Context\Scheduling\Domain\Event\PendingAppointmentCancelledIntegrationEvent;
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

        [$command, $close] = $bus->commands;
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
            array_map(static function (object $command): array {
                \assert($command instanceof ClosePortalRequestsForSubject);

                return [$command->subject, $command->subjectId, $command->reason, $command->clinicId];
            }, $bus->commands),
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
