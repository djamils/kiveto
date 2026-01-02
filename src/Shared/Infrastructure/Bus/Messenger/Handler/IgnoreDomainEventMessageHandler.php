<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Bus\Messenger\Handler;

use App\Shared\Application\Event\DomainEventMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Fallback handler to avoid "No handler" errors when no projection/reactor is registered yet.
 */
#[AsMessageHandler(bus: 'messenger.bus.event')]
final class IgnoreDomainEventMessageHandler
{
    public function __invoke(DomainEventMessage $message): void
    {
        // no-op
    }
}

