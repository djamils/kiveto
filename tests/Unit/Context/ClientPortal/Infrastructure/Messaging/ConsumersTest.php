<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Infrastructure\Messaging;

use App\Context\Client\Domain\Event\ClientArchivedIntegrationEvent;
use App\Context\ClientPortal\Application\Command\RevokeClientPortalAccess\RevokeClientPortalAccess;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmed;
use App\Context\ClientPortal\Domain\Event\ClientLinkConfirmedIntegrationEvent;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\ClientArchivedIntegrationEventConsumer;
use App\Context\ClientPortal\Infrastructure\Messaging\Consumer\ClientLinkConfirmedIntegrationBridge;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Application\Bus\IntegrationEventBusInterface;
use App\Shared\Application\Event\IntegrationEventPublisher;
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

        [$command] = $bus->commands;
        self::assertInstanceOf(RevokeClientPortalAccess::class, $command);
        self::assertSame(['clinic-1', 'client-1'], [$command->clinicId, $command->clientId]);
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
