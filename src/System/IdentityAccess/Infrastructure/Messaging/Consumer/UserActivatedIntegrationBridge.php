<?php

declare(strict_types=1);

namespace App\System\IdentityAccess\Infrastructure\Messaging\Consumer;

use App\Shared\Application\Event\IntegrationEventPublisher;
use App\System\IdentityAccess\Domain\Event\UserActivated;
use App\System\IdentityAccess\Domain\Event\UserActivatedIntegrationEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * An activation concerns Clinic (active veterinarians of the SMS quota):
 * it leaves through the outbox, in the activation's transaction.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final readonly class UserActivatedIntegrationBridge
{
    public function __construct(private IntegrationEventPublisher $integrationEventPublisher)
    {
    }

    public function __invoke(UserActivated $event): void
    {
        $this->integrationEventPublisher->publish(new UserActivatedIntegrationEvent(
            userId: $event->userId,
            occurredAt: $event->occurredAt,
        ));
    }
}
