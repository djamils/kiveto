<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Marker interface for integration events (cross-bounded contexts).
 * Routed to async transport for eventual processing.
 */
interface IntegrationEventInterface extends EventInterface
{
}
